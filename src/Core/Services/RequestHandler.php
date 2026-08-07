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

final class RequestHandler
{
    private const INVALID_TYPE_MESSAGE_FORMAT = '[%s] - This value should be of type %s';
    private const FINALLY_FAILURE_MESSAGE = 'JSON-RPC post-response stage failed';
    private const ACCESS_DENIED_MESSAGE = 'Access denied.';
    private const PLAIN_RESPONSE_IN_BATCH_MESSAGE = 'Internal error.';
    private const PLAIN_RESPONSE_IN_BATCH_INFO = 'Plain responses are not supported inside a batch request.';
    private const PROCESSOR_NOT_CALLABLE_MESSAGE = 'Internal error.';
    private const PROCESSOR_NOT_CALLABLE_INFO_FORMAT = 'Processor %s does not implement a callable RPC method.';

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

    public function applyStrategy(HandleBatchInterface $strategy, array $data, int $version, string $methodType): OvResponseInterface
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
        $batchProcessor = fn (mixed $item, int $itemVersion, string $itemMethodType): ?OvResponseInterface => $this->processBatch($item, $itemVersion, $itemMethodType, $isMultiBatch);

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
            match (true) {
                isset($baseRequest) && $baseRequest->hasId() => $id = $baseRequest->getId(),
                is_array($batch) && isset($batch['id']) => $id = $batch['id'],
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
        $constructorParams = [];
        foreach ($methodSpec->getRequiredParameters() as $requiredParameter) {
            $constructorParams[] = $baseRequest->getParams()[$requiredParameter['name']] ?? ($requiredParameter['defaultValue'] ?? null);
        }

        try {
            return new $requestClass(...$constructorParams);
        } catch (InvalidArgumentException|TypeError $e) {
            throw new JRPCException(
                'Invalid params.',
                JRPCException::INVALID_PARAMS,
                $e->getMessage(),
            );
        }
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
            } elseif (array_key_exists('defaultValue', $allParameter)) {
                $value = $allParameter['defaultValue'];
            } elseif ($name === 'params') {
                $value = $baseRequest->getParams();
            } else {
                continue;
            }

            $requestAdder = $methodSpec->getRequestAdders()[$name] ?? null;
            if (!is_null($requestAdder) && !empty($value)) {
                if (!is_array($value)) {
                    throw new JRPCException(
                        'Invalid params.',
                        JRPCException::INVALID_PARAMS,
                        sprintf('Parameter "%s" must be an array.', $name),
                    );
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
                            $elemVal = $this->prepareParametersFromClass($allParameter['type'], $elem, $allowExtraFields);
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
                if (class_exists($allParameter['type'])) {
                    if ($value !== null) {
                        try {
                            $value = $this->prepareParametersFromClass($allParameter['type'], $value, $allowExtraFields);
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

                if (is_null($value) && $name === 'params') {
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
     * @param class-string $class
     */
    private function prepareParametersFromClass(string $class, array|string $values, bool $allowExtraFields = false, int $depth = 0): object
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
            } catch (InvalidArgumentException|TypeError $e) {
                throw new JRPCException(
                    'Invalid params.',
                    JRPCException::INVALID_PARAMS,
                    $e->getMessage(),
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
            if ($setterParamType !== null && class_exists($setterArgumentType)) {
                $value = $this->prepareParametersFromClass($setterArgumentType, $value, $allowExtraFields, $depth + 1);
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
     * @throws JRPCException
     */
    private function processValidatorsForRequestInstance(MethodSpec $methodSpec, BaseRequest $baseRequest, mixed $requestInstance): void
    {
        $requestData = $baseRequest->getParams();

        foreach ($methodSpec->getValidators() as $field => $validatorItem) {
            if (class_exists($validatorItem['type'], false)) {
                $getter = $methodSpec->getRequestGetters()[$field];
                $requestData[$field] = $requestInstance->$getter();
            }
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
