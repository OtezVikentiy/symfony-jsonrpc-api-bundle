<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\Controller\ApiController;
use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcCallLoggerInterface;
use OV\JsonRPCAPIBundle\Core\Logging\NullJsonRpcCallLogger;
use OV\JsonRPCAPIBundle\Core\Services\ErrorSanitizer;
use OV\JsonRPCAPIBundle\Core\Services\HeadersPreparer;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler;
use OV\JsonRPCAPIBundle\Core\Services\RequestRawDataHandler;
use OV\JsonRPCAPIBundle\Core\Services\ResponseService;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\DataCollector\SerializerDataCollector;
use Symfony\Component\Serializer\Debug\TraceableNormalizer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractControllerTestCase extends TestCase
{
    private ?MethodSpecCollection $methodSpecCollection = null;
    private ?ServiceLocator $processorLocator = null;
    private ?ServiceLocator $serviceLocator = null;
    private ?ValidatorInterface $validator = null;
    private ?Security $security = null;
    private ?Request $request = null;
    private string $validateMethodExpectation = 'atLeastOnce';
    private ?HeadersPreparer $headersPreparer = null;
    private ?RequestHandler $requestHandler = null;
    private ?RequestRawDataHandler $requestRawDataHandler = null;
    private ?ResponseService $responseService = null;
    protected bool $allowExtraFields = false;

    /**
     * Multipart knobs. A test that leaves them alone builds exactly the JSON request this harness
     * has always built, so the whole existing suite keeps describing the transport it describes.
     */
    protected bool $sendAsMultipart = false;
    protected bool $omitMultipartEnvelope = false;
    protected bool $multipartEnabled = false;
    protected int $multipartMaxFiles = 10;

    /** @var array<string, mixed> */
    protected array $multipartFiles = [];
    protected bool $useRealValidator = false;
    protected ?JsonRpcCallLoggerInterface $callLoggerOverride = null;
    protected bool $isGranted = true;

    protected function tearDown(): void
    {
        $this->methodSpecCollection = null;
        $this->processorLocator = null;
        $this->serviceLocator = null;
        $this->validator = null;
        $this->security = null;
        $this->request = null;
        $this->headersPreparer = null;
        $this->requestHandler = null;
        $this->requestRawDataHandler = null;
        $this->responseService = null;
        $this->callLoggerOverride = null;
        $this->after();
    }

    protected function after(): void
    {
        return;
    }

    protected function executeControllerTest(
        array|string $data,
        ?MethodSpec $methodSpec = null,
        array $methodSpecs = [],
        int $version = 1,
        array $violationList = []
    ): mixed {
        if (empty($methodSpecs)) {
            $methodSpecs[] = $methodSpec;
        }

        $this->prepareRequest($data, $methodSpec, $version);
        $this->prepareServiceLocator();
        $this->prepareMethodSpecCollection($methodSpecs);
        $this->prepareValidator($violationList);
        $this->prepareSecurity();
        $this->prepareHeadersPreparer();
        $this->prepareResponseService();
        $this->prepareRequestHandler();
        $this->prepareRequestRawDataHandler();

        $controller = new ApiController();
        $controller->setContainer($this->serviceLocator);

        return $controller->index($this->request, $this->requestHandler, $this->requestRawDataHandler, $this->responseService, $this->callLoggerOverride ?? new NullJsonRpcCallLogger());
    }

    private function prepareResponseService(): void
    {
        $this->responseService = new ResponseService(
            $this->headersPreparer,
            new ErrorSanitizer(exposeInternalErrors: false),
        );
    }

    private function prepareRequestRawDataHandler(): void
    {
        $this->requestRawDataHandler = new RequestRawDataHandler(
            multipartEnabled: $this->multipartEnabled,
            multipartMaxFiles: $this->multipartMaxFiles,
        );
    }

    private function prepareRequestHandler(): void
    {
        $this->requestHandler = new RequestHandler(
            $this->security,
            $this->methodSpecCollection,
            $this->validator,
            $this->headersPreparer,
            $this->processorLocator,
            $this->responseService,
            $this->callLoggerOverride ?? new NullJsonRpcCallLogger(),
            allowExtraFields: $this->allowExtraFields,
        );
    }

    private function prepareHeadersPreparer(): void
    {
        $this->headersPreparer = new HeadersPreparer(['*']);
    }

    private function prepareRequest(array|string $data, ?MethodSpec $methodSpec = null, int $version = 1): void
    {
        $methodType = !is_null($methodSpec) ? $methodSpec->getRequestType() : 'POST';

        // A GET method takes its payload from the query string, not from a body, so a harness that
        // always fills the body can only ever exercise half the transports the bundle supports -
        // and a GET spec used to reach RequestRawDataHandler with an empty query bag and fail with
        // -32603 for reasons that had nothing to do with the test.
        $isGet = $methodType === Request::METHOD_GET;
        $queryData = is_array($data) ? $data : (json_decode($data, true) ?? []);
        $body = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data;

        // A real Request rather than a mock. The mock had to be told what every accessor
        // returns and had its bags assigned from outside, which is two descriptions of one
        // object that can disagree - and on Symfony 8 they stopped agreeing outright: the
        // bags became typed properties, so a doubled Request reached the code uninitialised
        // and every controller test came back -32603 for a reason that had nothing to do
        // with the bundle. Request::create() builds the query bag, QUERY_STRING and the
        // Content-Type header from one source, the way the framework does at runtime.
        $uri = sprintf('/api/v%d', $version);
        if ($isGet && is_array($queryData) && $queryData !== []) {
            $uri .= '?' . http_build_query($queryData);
        }

        if ($this->sendAsMultipart) {
            $this->request = $this->createMultipartRequest($uri, (string) $body);

            return;
        }

        $this->request = Request::create(
            $uri,
            $methodType,
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $body,
        );
    }

    /**
     * The body is left empty on purpose: PHP parses a multipart body into $_POST/$_FILES and leaves
     * php://input empty, so a Request carrying both would be a request no server ever produces.
     */
    private function createMultipartRequest(string $uri, string $envelope): Request
    {
        $fields = $this->omitMultipartEnvelope ? [] : ['jsonrpc' => $envelope];

        return Request::create(
            $uri,
            Request::METHOD_POST,
            $fields,
            [],
            $this->multipartFiles,
            ['CONTENT_TYPE' => 'multipart/form-data; boundary=--------boundary'],
            '',
        );
    }

    private function prepareMethodSpecCollection(array $methodSpecs): void
    {
        $methodSpecCollection = new MethodSpecCollection();

        $processorLocator = $this->createMock(ServiceLocator::class);
        $preProcessors = [];
        foreach ($methodSpecs as $methodSpec) {
            $class = $methodSpec->getMethodClass();
            $methodReflectionClass = new \ReflectionClass(new $class());
            $methodName = null;
            $attributes = $methodReflectionClass->getAttributes(JsonRPCAPI::class);
            foreach ($attributes as $attribute) {
                if ($attribute->getName() === JsonRPCAPI::class) {
                    $methodName = $attribute->getArguments()['methodName'];
                }
            }

            if (is_null($methodName)) {
                throw new \Exception('Could not define method name');
            }
            $methodSpecCollection->addMethodSpec(1, $methodName, $methodSpec);
            $preProcessors[] = [$class, new $class()];
        }

        $serializer = $this->serviceLocator->get('serializer');
        $processorLocator
            ->expects($this->any())
            ->method('has')
            ->with($this->identicalTo('serializer'))
            ->willReturn(true);
        $preProcessors[] = ['serializer', $serializer];
        $processorLocator
            ->expects($this->any())
            ->method('get')
            ->willReturnMap($preProcessors);

        $this->methodSpecCollection = $methodSpecCollection;
        $this->processorLocator = $processorLocator;
    }

    private function prepareServiceLocator(): void
    {
        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator
            ->expects($this->any())
            ->method('has')
            ->with($this->identicalTo('serializer'))
            ->willReturn(true);

        $jsonEncoder = new JsonEncoder();
        $normalizer = new TraceableNormalizer(new ObjectNormalizer(), new SerializerDataCollector());
        $serializer = new Serializer(normalizers: [$normalizer], encoders: [$jsonEncoder]);

        $serviceLocator
            ->expects($this->any())
            ->method('get')
            ->with($this->identicalTo('serializer'))
            ->willReturn($serializer);

        $this->serviceLocator = $serviceLocator;
    }

    private function prepareValidator(array $violationList = []): void
    {
        if ($this->useRealValidator) {
            $this->validator = Validation::createValidator();
            return;
        }

        $validator = $this->createMock(ValidatorInterface::class);
        $violations = new ConstraintViolationList();

        if (!empty($violationList)) {
            foreach ($violationList as $violationItem) {
                $violations->add(new ConstraintViolation($violationItem, '', [], null, '', null));
            }
        }

        $method = $this->validateMethodExpectation;
        $validator
            ->expects($this->$method())
            ->method('validate')
            ->willReturn($violations);

        $this->validator = $validator;
    }

    public function setValidateMethodExpectation(string $validateMethodExpectation): static
    {
        $this->validateMethodExpectation = $validateMethodExpectation;

        return $this;
    }

    private function prepareSecurity(): void
    {
        $security = $this->createMock(Security::class);
        $security
            ->expects($this->any())
            ->method('isGranted')
            ->willReturn($this->isGranted);

        $this->security = $security;
    }
}