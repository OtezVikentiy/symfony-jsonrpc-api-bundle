<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Core\Services;

use InvalidArgumentException;
use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcCallLoggerInterface;
use OV\JsonRPCAPIBundle\Core\PostProcessorInterface;
use OV\JsonRPCAPIBundle\Core\PreProcessorInterface;
use OV\JsonRPCAPIBundle\Core\Request\BaseRequest;
use OV\JsonRPCAPIBundle\Core\Request\PartialRequestInterface;
use OV\JsonRPCAPIBundle\Core\Response\BaseResponse;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;
use OV\JsonRPCAPIBundle\Core\Response\PlainResponseInterface;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler\HandleBatchInterface;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler\MultiBatchStrategy;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;
use TypeError;

/**
 * @internal
 */
final class RequestHandler
{
    private const INVALID_TYPE_MESSAGE_FORMAT = '[%s] - This value should be of type %s';
    private const CONSTRUCTOR_FAILURE_MESSAGE = 'One or more parameters have an unexpected type.';
    private const CONSTRUCTOR_ARGUMENT_POSITION_PATTERN = '/Argument #(\d+)/';
    private const POSITIONAL_PARAMS_FIELD = 'params';
    private const UNTYPED_TRANSPORT_METHOD = 'GET';
    private const FINALLY_FAILURE_MESSAGE = 'JSON-RPC post-response stage failed';
    private const ACCESS_DENIED_MESSAGE = 'Access denied.';
    private const PLAIN_RESPONSE_IN_BATCH_MESSAGE = 'Internal error.';
    private const PLAIN_RESPONSE_IN_BATCH_INFO = 'Plain responses are not supported inside a batch request.';
    private const PROCESSOR_NOT_CALLABLE_MESSAGE = 'Internal error.';
    private const PROCESSOR_NOT_CALLABLE_INFO_FORMAT = 'Processor %s does not implement a callable RPC method.';
    private const MULTIPART_NOT_ACCEPTED_INFO = 'Method does not accept multipart/form-data.';

    /**
     * A batch rejected for exceeding max_batch_size is, by definition, attacker-influenced and can be
     * arbitrarily large. Logging metadata about the rejection instead of the payload keeps the DoS
     * protection from becoming its own amplifier (masking + json_encode over the full rejected payload).
     */
    private const LOG_META_BATCH_REJECTED = 'batch_rejected';
    private const LOG_META_BATCH_SIZE = 'batch_size';
    private const LOG_META_MAX_BATCH_SIZE = 'max_batch_size';

    /** @var array<class-string, array<string, ReflectionMethod>> */
    private static array $setterIndexCache = [];

    public function __construct(
        private readonly Security $security,
        private readonly MethodSpecCollection $specCollection,
        private readonly ValidatorInterface $validator,
        private readonly HeadersPreparer $headersPreparer,
        private readonly ServiceLocator $processorLocator,
        private readonly ResponseService $responseService,
        private readonly JsonRpcCallLoggerInterface $callLogger,
        private readonly bool $strictNotifications = true,
        private readonly bool $allowExtraFields = false,
        private readonly int $maxBatchSize = 50,
        private readonly int $maxDtoDepth = 10,
        private readonly int $maxArrayParamSize = 1000,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function applyStrategy(HandleBatchInterface $strategy, array $data, int $version, string $methodType, bool $isMultipart = false): OvResponseInterface
    {
        if ($strategy instanceof MultiBatchStrategy && count($data) > $this->maxBatchSize) {
            $call = $this->callLogger->logRequest([
                self::LOG_META_BATCH_REJECTED => true,
                self::LOG_META_BATCH_SIZE => count($data),
                self::LOG_META_MAX_BATCH_SIZE => $this->maxBatchSize,
            ]);
            $err = $this->responseService->prepareErrorResponse(
                new JRPCException(
                    'Invalid Request.',
                    JRPCException::INVALID_REQUEST,
                    sprintf('Batch size %d exceeds limit %d.', count($data), $this->maxBatchSize),
                ),
                null,
            );
            $this->callLogger->logResponse($call, $err);

            return $err;
        }

        $isMultiBatch = $strategy instanceof MultiBatchStrategy;
        $batchProcessor = fn (mixed $item, int $itemVersion, string $itemMethodType): ?OvResponseInterface => $this->processBatch($item, $itemVersion, $itemMethodType, $isMultiBatch, $isMultipart);

        $response = $strategy->handleBatch($data, $version, $methodType, $batchProcessor);

        if ($response instanceof Response) {
            $response->headers->add($this->headersPreparer->prepareHeaders());
        }

        return $response;
    }

    public function processBatch(
        mixed $batch,
        int $version,
        string $methodType,
        bool $isBatchItem = false,
        bool $isMultipart = false,
    ): ?OvResponseInterface {
        $call = $this->callLogger->logRequest(is_array($batch) ? $batch : []);
        try {
            if (!is_array($batch)) {
                throw new JRPCException('Invalid Request.', JRPCException::INVALID_REQUEST);
            }

            $baseRequest = new BaseRequest($batch);

            $methodSpec = $this->specCollection->getMethodSpec($version, $baseRequest->getMethod());

            if ($methodSpec->getRequestType() !== $methodType) {
                throw new JRPCException('Invalid Request.', JRPCException::INVALID_REQUEST);
            }

            // The content-type assertion runs in prepareData(), before any method is known, so this
            // is the first moment the method's own opt-in can be consulted. Enabling multipart
            // globally must not silently open every existing method to a transport it was never
            // written for.
            if ($isMultipart && !$methodSpec->isAcceptsMultipart()) {
                throw new JRPCException(
                    'Invalid Request.',
                    JRPCException::INVALID_REQUEST,
                    self::MULTIPART_NOT_ACCEPTED_INFO,
                );
            }

            $this->checkRoles($methodSpec);

            $requestClass = $methodSpec->getRequest();
            $requestInstance = null;
            if (!is_null($requestClass)) {
                $requestInstance = $this->processRequestClass($methodSpec, $baseRequest, $requestClass);
            }

            $this->processValidatorsForRequestInstance($methodSpec, $baseRequest, $requestInstance);

            $processorClass = $methodSpec->getMethodClass();
            $processor = $this->processorLocator->get($processorClass);

            if (!is_object($processor) || !method_exists($processor, 'call')) {
                throw new JRPCException(
                    self::PROCESSOR_NOT_CALLABLE_MESSAGE,
                    JRPCException::INTERNAL_ERROR,
                    sprintf(self::PROCESSOR_NOT_CALLABLE_INFO_FORMAT, $processorClass),
                );
            }

            if ($methodSpec->isPreProcessorExists() && $processor instanceof PreProcessorInterface) {
                $this->runPreProcessors($processor, $processorClass, $requestInstance);
            }

            /** @var mixed|Response $response */
            $response = $processor->call($requestInstance);

            if ($isBatchItem && $response instanceof PlainResponseInterface) {
                throw new JRPCException(
                    self::PLAIN_RESPONSE_IN_BATCH_MESSAGE,
                    JRPCException::INTERNAL_ERROR,
                    self::PLAIN_RESPONSE_IN_BATCH_INFO,
                );
            }

            if ($methodSpec->isPlainResponse() && $response instanceof PlainResponseInterface) {
                if ($response instanceof Response) {
                    $response->headers->add($this->headersPreparer->prepareHeaders());
                }

                return $response;
            }

            if ($baseRequest->hasId() || (!$this->strictNotifications && !empty((array) $response))) {
                $response = $this->responseService->prepareJsonResponse(new BaseResponse($response, $baseRequest->getId()));

                return $response;
            }
        } catch (Throwable $e) {
            // Section 5: the id of a Response echoes the request's, and is Null when the request was
            // too malformed to establish one. BaseRequest may have thrown on this very id - it
            // rejects a boolean, an array or an object - so the raw value has to face the same test
            // before it is echoed, or the error response is itself invalid and the caller gets its
            // own arbitrary structure reflected back.
            match (true) {
                isset($baseRequest) && $baseRequest->hasId() => $id = $baseRequest->getId(),
                is_array($batch) && isset($batch['id']) && BaseRequest::isValidId($batch['id']) => $id = $batch['id'],
                default => $id = null,
            };

            $response = $this->responseService->prepareErrorResponse($e, $id);

            if (isset($baseRequest) && !$baseRequest->hasId()) {
                return null;
            }

            return $response;
        } finally {
            try {
                $loggedResponse = ($response ?? null) instanceof OvResponseInterface ? $response : null;
                $this->callLogger->logResponse($call, $loggedResponse);
                if (
                    isset($methodSpec)
                    && isset($processor)
                    && isset($processorClass)
                    && $methodSpec->isPostProcessorExists()
                    && $processor instanceof PostProcessorInterface
                ) {
                    $this->runPostProcessors($processor, $processorClass, $requestInstance ?? null, $loggedResponse);
                }
            } catch (Throwable $finallyFailure) {
                $this->logger?->error(self::FINALLY_FAILURE_MESSAGE, ['exception' => $finallyFailure]);
            }
        }

        return null;
    }

    private function runPreProcessors(
        PreProcessorInterface $processor,
        string $processorClass,
        ?object $requestInstance = null
    ): void {
        $preProcessors = $processor->getPreProcessors();

        if (!empty($preProcessors)) {
            foreach ($preProcessors as $processorClassName => $preProcessorsArr) {
                if ($processorClassName !== $processorClass) {
                    continue;
                }

                foreach ($preProcessorsArr as $func) {
                    if (method_exists($processor, $func)) {
                        $processor->$func($processorClass, $requestInstance);
                    }
                }
            }
        }
    }

    private function runPostProcessors(
        PostProcessorInterface $processor,
        string $processorClass,
        ?object $requestInstance = null,
        ?OvResponseInterface $response = null,
    ): void {
        $preProcessors = $processor->getPostProcessors();

        if (!empty($preProcessors)) {
            foreach ($preProcessors as $processorClassName => $preProcessorsArr) {
                if ($processorClassName !== $processorClass) {
                    continue;
                }

                foreach ($preProcessorsArr as $func) {
                    if (method_exists($processor, $func)) {
                        $processor->$func($processorClass, $requestInstance, $response);
                    }
                }
            }
        }
    }

    private function processRequestClass(MethodSpec $methodSpec, BaseRequest $baseRequest, string $requestClass): object
    {
        $requestInstance = $this->instantiateRequest($methodSpec, $baseRequest, $requestClass);

        return $this->hydrateRequest($requestInstance, $methodSpec, $baseRequest);
    }

    private function instantiateRequest(MethodSpec $methodSpec, BaseRequest $baseRequest, string $requestClass): object
    {
        $untypedTransport = $this->transportIsUntyped($methodSpec);
        $constructorParams = [];
        foreach ($methodSpec->getRequiredParameters() as $requiredParameter) {
            $value = $baseRequest->getParams()[$requiredParameter['name']] ?? ($requiredParameter['defaultValue'] ?? null);
            $constructorParams[] = $untypedTransport
                ? $this->coerceFromUntypedTransport($value, $requiredParameter['type'])
                : $value;
        }

        try {
            return new $requestClass(...$constructorParams);
        } catch (InvalidArgumentException|TypeError $e) {
            throw new JRPCException(
                'Invalid params.',
                JRPCException::INVALID_PARAMS,
                $this->describeConstructorFailure($e, $methodSpec->getRequiredParameters()),
            );
        }
    }

    /**
     * Turns an engine-generated constructor error into a message safe to hand back to the caller.
     *
     * The raw text carries the absolute path of the file that performed the call and the fully
     * qualified name of the request DTO, and it reaches the client untouched: ErrorSanitizer lets
     * JRPCException through by design, so expose_internal_errors never gets a say. Only the
     * argument position is taken from the message - everything the caller sees is then rebuilt from
     * the method specification, in the same format the hydration path already uses.
     *
     * @param array<int, array{name: string, type: string}> $requiredParameters
     */
    private function describeConstructorFailure(Throwable $failure, array $requiredParameters): string
    {
        if (preg_match(self::CONSTRUCTOR_ARGUMENT_POSITION_PATTERN, $failure->getMessage(), $matches) !== 1) {
            return self::CONSTRUCTOR_FAILURE_MESSAGE;
        }

        $parameter = $requiredParameters[((int) $matches[1]) - 1] ?? null;

        if ($parameter === null) {
            return self::CONSTRUCTOR_FAILURE_MESSAGE;
        }

        return sprintf(self::INVALID_TYPE_MESSAGE_FORMAT, $parameter['name'], $parameter['type']);
    }

    /**
     * Whether this field is the by-position pseudo-field and the caller actually sent by-position.
     *
     * Same test the validator side uses: section 4.2 says by-position means an Array, which
     * json_decode gives as a list. An empty payload is a list too, and hydrating `params` from it
     * yields the empty array the default would have produced anyway.
     */
    private function isPositionalPayloadFor(string $name, BaseRequest $baseRequest): bool
    {
        return $name === self::POSITIONAL_PARAMS_FIELD && array_is_list($baseRequest->getParams());
    }

    /**
     * Whether the payload arrived over a transport that cannot express types.
     *
     * A GET request has no body: its payload comes from the query string, and PHP parses a query
     * string into strings, every value of it. Refusing `?id=5` for an `int $id` would punish the
     * caller for a limitation of the transport rather than for a mistake - there is nothing in a
     * query string a caller could have typed correctly. A JSON body is the opposite case: it carries
     * real types, so `"42"` where `42` belongs is a genuine client error and stays an error.
     */
    private function transportIsUntyped(MethodSpec $methodSpec): bool
    {
        return $methodSpec->getRequestType() === self::UNTYPED_TRANSPORT_METHOD;
    }

    /**
     * Reads a string the way the declared type would have been written in JSON.
     *
     * Deliberately narrow. Only an unambiguous representation is converted, and anything else is
     * handed on untouched so the validator refuses it exactly as before - `?id=abc` for an int must
     * still be -32602, because that one really is the caller's mistake. Booleans use PHP's own
     * filter, so the spellings accepted are the familiar 1/true/on/yes and 0/false/off/no.
     */
    private function coerceFromUntypedTransport(mixed $value, string $type): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $coerced = match ($type) {
            'int', 'integer' => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
            'float', 'double' => filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE),
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            default => null,
        };

        return $coerced ?? $value;
    }

    private function hydrateRequest(object $requestInstance, MethodSpec $methodSpec, BaseRequest $baseRequest): object
    {
        $tracksProvided = $requestInstance instanceof PartialRequestInterface;
        $allowExtraFields = $this->isExtraFieldsAllowed($methodSpec);
        $invalidTypeErrors = [];

        foreach ($methodSpec->getAllParameters() as $allParameter) {
            $name = $allParameter['name'];
            $wasProvided = false;

            if (array_key_exists($name, $baseRequest->getParams())) {
                $value = $baseRequest->getParams()[$name];
                $wasProvided = true;
            } elseif ($this->isPositionalPayloadFor($name, $baseRequest)) {
                // Ahead of the default, because a caller who sent by-position parameters sent a
                // value - and a DTO written the natural way, `private array $params = []`, carries
                // a default that used to win. Every positional argument was then dropped for the
                // empty array, silently: no error, no log, just a method called with nothing.
                $value = $baseRequest->getParams();
                $wasProvided = true;
            } elseif (array_key_exists('defaultValue', $allParameter)) {
                $value = $allParameter['defaultValue'];
            } elseif ($name === self::POSITIONAL_PARAMS_FIELD) {
                $value = $baseRequest->getParams();
            } else {
                continue;
            }

            if ($this->transportIsUntyped($methodSpec)) {
                $value = $this->coerceFromUntypedTransport($value, $allParameter['type']);
            }

            $requestAdder = $methodSpec->getRequestAdders()[$name] ?? null;

            // The `|| is_array($value)` is what lets an empty list through. !empty([]) is false, so
            // [] used to fall to the setter branch - where CompilerPass has already rewritten the
            // declared type to the type of an *element*, so the empty array was built into one bare
            // element and handed to a setter expecting the collection. The caller got
            // "[items] - This value should be of type Item" and no way at all to send []. Every
            // other value keeps the branch it had: null and 0 still go to the setter, a string still
            // enters here and is refused by the check below.
            if (!is_null($requestAdder) && (!empty($value) || is_array($value))) {
                if (!is_array($value)) {
                    throw new JRPCException(
                        'Invalid params.',
                        JRPCException::INVALID_PARAMS,
                        sprintf('Parameter "%s" must be an array.', $name),
                    );
                }

                // An adder appends, so zero elements leave a typed collection property holding
                // nothing at all, and the first read of it raises an Error - reported as -32603.
                // "No items" is an answer; the setter is what states it.
                if ($value === []) {
                    $collectionSetter = $methodSpec->getRequestSetters()[$name] ?? null;
                    if (!is_null($collectionSetter)) {
                        try {
                            $requestInstance->$collectionSetter([]);
                        } catch (InvalidArgumentException|TypeError) {
                            // A collection held in an object rather than an array - a Doctrine
                            // ArrayCollection, or any custom type - rejects the empty array its
                            // setter is being handed. The branch this replaced sat inside the same
                            // guard, so without it a caller sending [] was told the server had
                            // failed, which is the -32603 the surrounding work exists to stop.
                            $invalidTypeErrors[] = sprintf(
                                self::INVALID_TYPE_MESSAGE_FORMAT,
                                $name,
                                $allParameter['type'],
                            );
                        }
                    }

                    if ($tracksProvided && $wasProvided) {
                        $requestInstance->markProvided($name);
                    }

                    continue;
                }
                if (count($value) > $this->maxArrayParamSize) {
                    throw new JRPCException(
                        'Invalid params.',
                        JRPCException::INVALID_PARAMS,
                        sprintf('Array parameter "%s" size %d exceeds limit %d.', $name, count($value), $this->maxArrayParamSize),
                    );
                }
                $adderFailed = false;

                if (class_exists($allParameter['type'])) {
                    foreach ($value as $elem) {
                        if (!is_array($elem) && !is_string($elem)) {
                            $invalidTypeErrors[] = sprintf(
                                self::INVALID_TYPE_MESSAGE_FORMAT,
                                $name,
                                $allParameter['type'],
                            );
                            $adderFailed = true;
                            continue;
                        }

                        try {
                            $elemVal = $this->prepareParametersFromClass($allParameter['type'], $elem, $allowExtraFields, 0, $this->transportIsUntyped($methodSpec));
                        } catch (InvalidArgumentException|TypeError) {
                            $invalidTypeErrors[] = sprintf(
                                self::INVALID_TYPE_MESSAGE_FORMAT,
                                $name,
                                $allParameter['type'],
                            );
                            $adderFailed = true;
                            continue;
                        }

                        try {
                            $requestInstance->$requestAdder($elemVal);
                        } catch (InvalidArgumentException|TypeError) {
                            $invalidTypeErrors[] = sprintf(
                                self::INVALID_TYPE_MESSAGE_FORMAT,
                                $name,
                                $allParameter['type'],
                            );
                            $adderFailed = true;
                        }
                    }
                } else {
                    foreach ($value as $elem) {
                        try {
                            $requestInstance->$requestAdder($elem);
                        } catch (InvalidArgumentException|TypeError) {
                            $invalidTypeErrors[] = sprintf(
                                self::INVALID_TYPE_MESSAGE_FORMAT,
                                $name,
                                $allParameter['type'],
                            );
                            $adderFailed = true;
                        }
                    }
                }

                if ($tracksProvided && $wasProvided && !$adderFailed) {
                    $requestInstance->markProvided($name);
                }

                continue;
            }

            $requestSetter = $methodSpec->getRequestSetters()[$name] ?? null;
            if (!is_null($requestSetter)) {
                if (class_exists($allParameter['type']) && !self::alreadyOfDeclaredType($value, $allParameter['type'])) {
                    if ($value !== null) {
                        try {
                            $value = $this->prepareParametersFromClass($allParameter['type'], $value, $allowExtraFields, 0, $this->transportIsUntyped($methodSpec));
                        } catch (InvalidArgumentException|TypeError) {
                            $invalidTypeErrors[] = sprintf(
                                self::INVALID_TYPE_MESSAGE_FORMAT,
                                $name,
                                $allParameter['type'],
                            );
                            continue;
                        }
                    }
                }

                if (is_null($value) && $name === self::POSITIONAL_PARAMS_FIELD) {
                    $value = $baseRequest->getParams();
                }

                try {
                    $requestInstance->$requestSetter($value);
                } catch (InvalidArgumentException|TypeError) {
                    $invalidTypeErrors[] = sprintf(
                        self::INVALID_TYPE_MESSAGE_FORMAT,
                        $name,
                        $allParameter['type'],
                    );
                    continue;
                }

                if ($tracksProvided && $wasProvided) {
                    $requestInstance->markProvided($name);
                }
            }
        }

        if (!empty($invalidTypeErrors)) {
            throw new JRPCException(
                'Invalid params.',
                JRPCException::INVALID_PARAMS,
                implode(PHP_EOL, $invalidTypeErrors),
            );
        }

        return $requestInstance;
    }

    /**
     * Whether the transport already produced a value of the type the DTO declares.
     *
     * Both nested-DTO branches assume the value is raw JSON - an array of properties, or a string
     * for a single-argument constructor - and build the declared class out of it. That assumption
     * does not hold for a value the transport itself constructed: multipart hydration puts an
     * UploadedFile straight into params, and passing that to prepareParametersFromClass() would
     * try to build an UploadedFile out of an UploadedFile. Stated as an invariant rather than as an
     * UploadedFile special case, so any future transport-produced type is covered by the same rule.
     */
    private static function alreadyOfDeclaredType(mixed $value, string $type): bool
    {
        return $value instanceof $type;
    }

    /**
     * @param class-string $class
     */
    private function prepareParametersFromClass(string $class, array|string $values, bool $allowExtraFields = false, int $depth = 0, bool $untypedTransport = false): object
    {
        if ($depth > $this->maxDtoDepth) {
            throw new JRPCException(
                'Invalid params.',
                JRPCException::INVALID_PARAMS,
                sprintf('DTO nesting depth %d exceeds limit %d.', $depth, $this->maxDtoDepth),
            );
        }

        if (is_string($values)) {
            try {
                return new $class($values);
            } catch (InvalidArgumentException|TypeError) {
                throw new JRPCException(
                    'Invalid params.',
                    JRPCException::INVALID_PARAMS,
                    self::CONSTRUCTOR_FAILURE_MESSAGE,
                );
            }
        }

        $parametersClass = new $class();
        $tracksProvided = $parametersClass instanceof PartialRequestInterface;

        $methodsIdx = self::$setterIndexCache[$class] ??= self::buildSetterIndex($class);

        $invalidTypeErrors = [];
        foreach ($values as $name => $value) {
            $setterName = 'set' . ucfirst($name);

            if (!isset($methodsIdx[$setterName]) || !$methodsIdx[$setterName]->isPublic()) {
                if ($allowExtraFields) {
                    continue;
                }
                throw new JRPCException('Invalid params.', JRPCException::INVALID_PARAMS, sprintf('Parameters %s is not expected in request.', $name));
            }

            $setter = $methodsIdx[$setterName];
            $setterParamType = $setter->getParameters()[0]->getType();
            $setterArgumentType = $setterParamType instanceof ReflectionNamedType ? $setterParamType->getName() : 'mixed';
            if ($setterParamType !== null && class_exists($setterArgumentType) && !self::alreadyOfDeclaredType($value, $setterArgumentType)) {
                $value = $this->prepareParametersFromClass($setterArgumentType, $value, $allowExtraFields, $depth + 1, $untypedTransport);
            } elseif ($untypedTransport) {
                $value = $this->coerceFromUntypedTransport($value, $setterArgumentType);
            }

            try {
                $parametersClass->$setterName($value);
            } catch (InvalidArgumentException|TypeError) {
                $invalidTypeErrors[] = sprintf(self::INVALID_TYPE_MESSAGE_FORMAT, $name, $setterArgumentType);
                continue;
            }

            if ($tracksProvided) {
                $parametersClass->markProvided($name);
            }
        }

        if (!empty($invalidTypeErrors)) {
            throw new JRPCException('Invalid params.', JRPCException::INVALID_PARAMS, implode(PHP_EOL, $invalidTypeErrors));
        }

        return $parametersClass;
    }

    /**
     * Presents by-position parameters to the validator the way hydration already sees them.
     *
     * Spec section 4.2 allows params to be an Array, and a request DTO receives that array through a
     * single property named `params`. hydrateRequest() has always understood that pseudo-field; the
     * validator did not, so it saw a list keyed 0..n, reported `params` as missing and every element
     * as unexpected, and rejected every by-position call with -32602. The wrapping happens only when
     * the method actually declares the pseudo-field and the payload carries no literal `params` key,
     * which is the same precedence hydration applies.
     *
     * @return array<mixed>
     */
    private function mapParamsOntoValidatedFields(MethodSpec $methodSpec, BaseRequest $baseRequest): array
    {
        $params = $baseRequest->getParams();

        if (!array_key_exists(self::POSITIONAL_PARAMS_FIELD, $methodSpec->getValidators())) {
            return $params;
        }

        // The test is whether the payload is by-position, and section 4.2 says that means an Array -
        // which json_decode gives as a list. Keying on "there is no literal params key" instead was
        // wrong: a DTO may declare params alongside other fields, and then a perfectly ordinary
        // by-name call such as {"other":"x"} was swallowed whole into the pseudo-field, leaving
        // every named field it did send reported as missing.
        if (!array_is_list($params)) {
            return $params;
        }

        return [self::POSITIONAL_PARAMS_FIELD => $params];
    }

    private function isFieldInitialised(mixed $requestInstance, string $field): bool
    {
        if (!is_object($requestInstance)) {
            return false;
        }

        $reflection = new ReflectionClass($requestInstance);

        if (!$reflection->hasProperty($field)) {
            return true;
        }

        return $reflection->getProperty($field)->isInitialized($requestInstance);
    }

    /**
     * @throws JRPCException
     */
    private function processValidatorsForRequestInstance(MethodSpec $methodSpec, BaseRequest $baseRequest, mixed $requestInstance): void
    {
        $requestData = $this->mapParamsOntoValidatedFields($methodSpec, $baseRequest);

        // The validator inspects the payload, not the hydrated object, so it has to see the same
        // values hydration will. Without this a coerced field passed into the DTO correctly and was
        // then rejected by the validator that was still looking at the original string.
        if ($this->transportIsUntyped($methodSpec)) {
            foreach ($methodSpec->getValidators() as $field => $validatorItem) {
                if (array_key_exists($field, $requestData)) {
                    $requestData[$field] = $this->coerceFromUntypedTransport($requestData[$field], $validatorItem['type']);
                }
            }
        }

        foreach ($methodSpec->getValidators() as $field => $validatorItem) {
            if (!class_exists($validatorItem['type'], false)) {
                continue;
            }

            // A field the caller omitted was never hydrated, so its typed property holds nothing at
            // all. Reading it raises "must not be accessed before initialization" - an Error, not a
            // JRPCException, which ErrorSanitizer then reported as -32603 Internal error. A caller
            // who forgot a required field was being told the server had broken. Leaving the field
            // out of the data lets the validator do its job and say what it is: -32602, naming it.
            if (!$this->isFieldInitialised($requestInstance, $field)) {
                continue;
            }

            $getter = $methodSpec->getRequestGetters()[$field];
            $requestData[$field] = $requestInstance->$getter();
        }

        $allowExtraFields = $this->isExtraFieldsAllowed($methodSpec);

        $violations = $this->validator->validate(
            $requestData,
            new Assert\Collection(fields: $methodSpec->getCompiledValidators(), allowExtraFields: $allowExtraFields)
        );

        if ($violations->count()) {
            $errs = [];

            foreach ($violations as $violation) {
                $errs[] = sprintf('%s - %s', $violation->getPropertyPath(), $violation->getMessage());
            }

            throw new JRPCException('Invalid params.', JRPCException::INVALID_PARAMS, implode(PHP_EOL, $errs));
        }
    }

    /**
     * @param class-string $class
     *
     * @return array<string, ReflectionMethod>
     */
    private static function buildSetterIndex(string $class): array
    {
        $methodsIdx = [];
        foreach ((new ReflectionClass($class))->getMethods() as $method) {
            $methodsIdx[$method->getName()] = $method;
        }

        return $methodsIdx;
    }

    private function isExtraFieldsAllowed(MethodSpec $methodSpec): bool
    {
        return $this->allowExtraFields || $methodSpec->isAllowExtraFields();
    }

    /**
     * @throws JRPCException
     */
    private function checkRoles(MethodSpec $methodSpec): void
    {
        if (empty($methodSpec->getRoles())) {
            return;
        }

        foreach ($methodSpec->getRoles() as $role) {
            if ($this->security->isGranted($role)) {
                return;
            }
        }

        throw new JRPCException(self::ACCESS_DENIED_MESSAGE, JRPCException::SERVER_ERROR);
    }
}
