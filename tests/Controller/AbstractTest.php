<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use Doctrine\Common\Annotations\AnnotationReader;
use OV\JsonRPCAPIBundle\Controller\ApiController;
use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\Core\Services\HeadersPreparer;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler;
use OV\JsonRPCAPIBundle\Core\Services\RequestRawDataHandler;
use OV\JsonRPCAPIBundle\Core\Services\ResponseService;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\DataCollector\SerializerDataCollector;
use Symfony\Component\Serializer\Debug\TraceableNormalizer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractTest extends TestCase
{
    private ?MethodSpecCollection $methodSpecCollection = null;
    private ?Container $container = null;
    private ?ServiceLocator $serviceLocator = null;
    private ?ValidatorInterface $validator = null;
    private ?Security $security = null;
    private ?Request $request = null;
    private string $validateMethodExpectation = 'atLeastOnce';
    private ?HeadersPreparer $headersPreparer = null;
    private ?RequestHandler $requestHandler = null;
    private ?RequestRawDataHandler $requestRawDataHandler = null;
    private ?ResponseService $responseService = null;

    protected function tearDown(): void
    {
        $this->methodSpecCollection = null;
        $this->container = null;
        $this->serviceLocator = null;
        $this->validator = null;
        $this->security = null;
        $this->request = null;
        $this->headersPreparer = null;
        $this->requestHandler = null;
        $this->requestRawDataHandler = null;
        $this->responseService = null;
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

        return $controller->index($this->request, $this->requestHandler, $this->requestRawDataHandler, $this->responseService);
    }

    private function prepareResponseService(): void
    {
        $this->responseService = new ResponseService($this->headersPreparer);
    }

    private function prepareRequestRawDataHandler(): void
    {
        $this->requestRawDataHandler = new RequestRawDataHandler();
    }

    private function prepareRequestHandler(): void
    {
        $this->requestHandler = new RequestHandler(
            $this->security,
            $this->methodSpecCollection,
            $this->validator,
            $this->headersPreparer,
            $this->container,
            $this->responseService,
        );
    }

    private function prepareHeadersPreparer(): void
    {
        $this->headersPreparer = new HeadersPreparer(['*']);
    }

    private function prepareRequest(array|string $data, ?MethodSpec $methodSpec = null, int $version = 1): void
    {
        $request = $this->createMock(Request::class);
        $request->request = new InputBag([]);
        $request
            ->expects($this->any())
            ->method('getMethod')
            ->willReturn(!is_null($methodSpec) ? $methodSpec->getRequestType() : 'POST');
        $request
            ->expects($this->any())
            ->method('getPathInfo')
            ->willReturn(sprintf('/api/v%d', $version));
        $request
            ->expects($this->any())
            ->method('getContent')
            ->willReturn(is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data);

        $this->request = $request;
    }

    private function prepareMethodSpecCollection(array $methodSpecs): void
    {
        $annotationReader = new AnnotationReader();
        $methodSpecCollection = new MethodSpecCollection();

        $container = $this->createMock(Container::class);
        $callbacks = [];
        foreach ($methodSpecs as $methodSpec) {
            $class = $methodSpec->getMethodClass();
            $methodReflectionClass = new \ReflectionClass(new $class());
            $classAnnotation = $annotationReader->getClassAnnotation($methodReflectionClass, JsonRPCAPI::class);
            $methodName = null;
            if (!is_null($classAnnotation)) {
                $methodName = $classAnnotation->getMethodName();
            } else {
                $attributes = $methodReflectionClass->getAttributes(JsonRPCAPI::class);
                foreach ($attributes as $attribute) {
                    if ($attribute->getName() === JsonRPCAPI::class) {
                        $methodName = $attribute->getArguments()['methodName'];
                    }
                }
            }

            if (is_null($methodName)) {
                throw new \Exception('Could not define method name');
            }
            $methodSpecCollection->addMethodSpec(1, $methodName, $methodSpec);
            $callbacks[] = [$class, 1, new $class()];
        }

        $serializer = $this->serviceLocator->get('serializer');
        $container
            ->expects($this->any())
            ->method('has')
            ->with($this->identicalTo('serializer'))
            ->willReturn(true);
        $callbacks[] = ['serializer', 1, $serializer];
        $container
            ->expects($this->any())
            ->method('get')
            ->willReturnMap($callbacks);

        $this->methodSpecCollection = $methodSpecCollection;
        $this->container = $container;
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

    public function setValidateMethodExpectation(string $validateMethodExpectation): AbstractTest
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
            ->willReturn(true);

        $this->security = $security;
    }
}