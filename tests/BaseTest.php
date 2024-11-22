<?php
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\Tests;

use Doctrine\Common\Annotations\AnnotationReader;
use Exception;
use OV\JsonRPCAPIBundle\Controller\ApiController;
use OV\JsonRPCAPIBundle\DependencyInjection\{MethodSpec, MethodSpecCollection};
use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\RPC\V1\GetData\GetDataRequest;
use OV\JsonRPCAPIBundle\RPC\V1\GetDataMethod;
use OV\JsonRPCAPIBundle\RPC\V1\NotifyHello\NotifyHelloRequest;
use OV\JsonRPCAPIBundle\RPC\V1\NotifyHelloMethod;
use OV\JsonRPCAPIBundle\RPC\V1\NotifySum\NotifySumRequest;
use OV\JsonRPCAPIBundle\RPC\V1\NotifySumMethod;
use OV\JsonRPCAPIBundle\RPC\V1\Subtract\SubtractRequest;
use OV\JsonRPCAPIBundle\RPC\V1\Subtract2\Subtract2Request;
use OV\JsonRPCAPIBundle\RPC\V1\Subtract2Method;
use OV\JsonRPCAPIBundle\RPC\V1\SubtractMethod;
use OV\JsonRPCAPIBundle\RPC\V1\Sum\SumRequest;
use OV\JsonRPCAPIBundle\RPC\V1\SumMethod;
use OV\JsonRPCAPIBundle\RPC\V1\Test\TestRequest;
use OV\JsonRPCAPIBundle\RPC\V1\TestMethod;
use OV\JsonRPCAPIBundle\RPC\V1\Update\UpdateRequest;
use OV\JsonRPCAPIBundle\RPC\V1\UpdateMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\{InputBag, JsonResponse, Request};
use Symfony\Component\Serializer\DataCollector\SerializerDataCollector;
use Symfony\Component\Serializer\Debug\TraceableNormalizer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BaseTest extends TestCase
{
    public function testCreateRequest()
    {
        $request = new TestRequest(1);

        $this->assertSame(1, $request->getId());
    }

    public function testController()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'params' => [
                'title' => 'AZAZAZA',
            ],
            'id' => '1',
        ];
        $methodSpec = new MethodSpec(
            TestMethod::class,
            'POST',
            '',
            '',
            false,
            'test',
            [['name' => 'id', 'type' => 'int'], ['name' => 'title', 'type' => 'string']],
            [['name' => 'id', 'type' => 'int']],
            TestRequest::class,
            ['id' => 'setId', 'title' => 'setTitle'],
            ['id' => ['allowsNull' => false, 'type' => 'int'], 'title' => ['allowsNull' => false, 'type' => 'string']]
        );
        $responseData = [
            'jsonrpc' => '2.0',
            'result' => [
                'title' => 'AZAZAZA',
                'success' => true,
            ],
            'id' => '1',
        ];
        [$serviceLocator, $request, $methodSpecCollection, $validator, $container] = $this->prepare($data, $methodSpec);

        $security = $this->createMock(Security::class);
        $security
            ->expects($this->any())
            ->method('isGranted')
            ->willReturn(true);

        $controller = new ApiController(['*'], $security);
        $controller->setContainer($serviceLocator);

        $result = $controller->index($request, $methodSpecCollection, $validator, $container);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }

    public function testController2()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'params' => [
                'title' => 'AZAZAZA',
            ],
            'id' => '1',
        ];
        $methodSpec = new MethodSpec(
            TestMethod::class,
            'POST',
            '',
            '',
            false,
            'test',
            [['name' => 'id', 'type' => 'int'], ['name' => 'title', 'type' => 'string']],
            [['name' => 'id', 'type' => 'int']],
            TestRequest::class,
            ['id' => 'setId', 'title' => 'setTitle'],
            ['id' => ['allowsNull' => false, 'type' => 'int'], 'title' => ['allowsNull' => false, 'type' => 'string']]
        );
        $responseData = [
            'jsonrpc' => '2.0',
            'result' => [
                'title' => 'AZAZAZA',
                'success' => true,
            ],
            'id' => '1',
        ];
        [$serviceLocator, $request, $methodSpecCollection, $validator, $container] = $this->prepare($data, $methodSpec);

        $security = $this->createMock(Security::class);
        $security
            ->expects($this->any())
            ->method('isGranted')
            ->willReturn(true);

        $controller = new ApiController([], $security);
        $controller->setContainer($serviceLocator);

        $result = $controller->index($request, $methodSpecCollection, $validator, $container);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }

    public function testRpcCallWithPositionalParameters()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'subtract',
            'params' => [42, 23],
            'id' => '2',
        ];
        $methodSpec = new MethodSpec(
            SubtractMethod::class,
            'POST',
            '',
            '',
            false,
            'subtract',
            [['name' => 'params', 'type' => 'array']],
            [],
            SubtractRequest::class,
            ['params' => 'setParams'],
            ['params' => ['allowsNull' => false, 'type' => 'array']]
        );
        $responseData = [
            'jsonrpc' => '2.0',
            'result' => 19,
            'id' => '2',
        ];
        [$serviceLocator, $request, $methodSpecCollection, $validator, $container] = $this->prepare($data, $methodSpec);

        $security = $this->createMock(Security::class);
        $security
            ->expects($this->any())
            ->method('isGranted')
            ->willReturn(true);

        $controller = new ApiController(['*'], $security);
        $controller->setContainer($serviceLocator);

        $result = $controller->index($request, $methodSpecCollection, $validator, $container);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }

    public function testRpcCallWithPositionalParameters2()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'subtract',
            'params' => [23, 42],
            'id' => '3',
        ];
        $methodSpec = new MethodSpec(
            SubtractMethod::class,
            'POST',
            '',
            '',
            false,
            'subtract',
            [['name' => 'params', 'type' => 'array']],
            [],
            SubtractRequest::class,
            ['params' => 'setParams'],
            ['params' => ['allowsNull' => false, 'type' => 'array']]
        );
        $responseData = [
            'jsonrpc' => '2.0',
            'result' => -19,
            'id' => '3',
        ];
        [$serviceLocator, $request, $methodSpecCollection, $validator, $container] = $this->prepare($data, $methodSpec);

        $security = $this->createMock(Security::class);
        $security
            ->expects($this->any())
            ->method('isGranted')
            ->willReturn(true);

        $controller = new ApiController(['*'], $security);
        $controller->setContainer($serviceLocator);

        $result = $controller->index($request, $methodSpecCollection, $validator, $container);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }

    public function testRpcCallWithNamedParameters()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'subtract2',
            'params' => [
                'subtrahend' => 23,
                'minuend' => 42,
            ],
            'id' => '4',
        ];
        $methodSpec = new MethodSpec(
            Subtract2Method::class,
            'POST',
            '',
            '',
            false,
            'subtract2',
            [['name' => 'subtrahend', 'type' => 'int'], ['name' => 'minuend', 'type' => 'int']],
            [],
            Subtract2Request::class,
            ['subtrahend' => 'setSubtrahend', 'minuend' => 'setMinuend'],
            ['subtrahend' => ['allowsNull' => false, 'type' => 'int'], 'minuend' => ['allowsNull' => false, 'type' => 'int']]
        );
        $responseData = [
            'jsonrpc' => '2.0',
            'result' => 19,
            'id' => '4',
        ];
        [$serviceLocator, $request, $methodSpecCollection, $validator, $container] = $this->prepare($data, $methodSpec);

        $security = $this->createMock(Security::class);
        $security
            ->expects($this->any())
            ->method('isGranted')
            ->willReturn(true);

        $controller = new ApiController(['*'], $security);
        $controller->setContainer($serviceLocator);

        $result = $controller->index($request, $methodSpecCollection, $validator, $container);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }

    public function testRpcCallWithNamedParameters2()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'subtract2',
            'params' => [
                'minuend' => 42,
                'subtrahend' => 23,
            ],
            'id' => '4',
        ];
        $methodSpec = new MethodSpec(
            Subtract2Method::class,
            'POST',
            '',
            '',
            false,
            'subtract2',
            [['name' => 'subtrahend', 'type' => 'int'], ['name' => 'minuend', 'type' => 'int']],
            [],
            Subtract2Request::class,
            ['subtrahend' => 'setSubtrahend', 'minuend' => 'setMinuend'],
            ['subtrahend' => ['allowsNull' => false, 'type' => 'int'], 'minuend' => ['allowsNull' => false, 'type' => 'int']]
        );
        $responseData = [
            'jsonrpc' => '2.0',
            'result' => 19,
            'id' => '4',
        ];
        [$serviceLocator, $request, $methodSpecCollection, $validator, $container] = $this->prepare($data, $methodSpec);

        $security = $this->createMock(Security::class);
        $security
            ->expects($this->any())
            ->method('isGranted')
            ->willReturn(true);

        $controller = new ApiController(['*'], $security);
        $controller->setContainer($serviceLocator);

        $result = $controller->index($request, $methodSpecCollection, $validator, $container);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }

    public function testNotification()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'update',
            'params' => [1, 2, 3, 4, 5],
        ];
        $methodSpec = new MethodSpec(
            UpdateMethod::class,
            'PUT',
            '',
            '',
            false,
            'update',
            [['name' => 'params', 'type' => 'array']],
            [],
            UpdateRequest::class,
            ['params' => 'setParams'],
            ['params' => ['allowsNull' => false, 'type' => 'array']]
        );
        $responseData = '{}';
        [$serviceLocator, $request, $methodSpecCollection, $validator, $container] = $this->prepare($data, $methodSpec);

        $security = $this->createMock(Security::class);
        $security
            ->expects($this->any())
            ->method('isGranted')
            ->willReturn(true);

        $controller = new ApiController(['*'], $security);
        $controller->setContainer($serviceLocator);

        $result = $controller->index($request, $methodSpecCollection, $validator, $container);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals($responseData, $result->getContent());
    }

    private function prepare(array $data, MethodSpec $methodSpec)
    {
        $request = $this->createMock(Request::class);
        $request->request = new InputBag([]);
        $request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn($methodSpec->getRequestType());
        $request
            ->expects($this->once())
            ->method('getPathInfo')
            ->willReturn('/api/v1');
        $request
            ->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode($data, JSON_UNESCAPED_UNICODE));

        $annotationReader = new AnnotationReader();
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
            throw new Exception('Could not define method name');
        }

        $methodSpecCollection = new MethodSpecCollection();
        $methodSpecCollection->addMethodSpec(1, $methodName, $methodSpec);

        $validator = $this->createMock(ValidatorInterface::class);
        $violations = new ConstraintViolationList();
        $validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn($violations);

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator
            ->expects($this->any())
            ->method('has')
            ->willReturn(true);

        $jsonEncoder = new JsonEncoder();
        $normalizer = new TraceableNormalizer(new ObjectNormalizer(), new SerializerDataCollector());
        $serializer = new Serializer(normalizers: [$normalizer], encoders: [$jsonEncoder]);

        $serviceLocator
            ->expects($this->any())
            ->method('get')
            ->willReturn($serializer);

        $container = $this->createMock(Container::class);
        $methodClass = $methodSpec->getMethodClass();
        $container
            ->expects($this->once())
            ->method('get')
            ->willReturn(new $methodClass());

        return [$serviceLocator, $request, $methodSpecCollection, $validator, $container];
    }

    public function testNonExistentMethod()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'foobar',
            'id' => '1',
        ];
        $methodSpec = new MethodSpec(
            UpdateMethod::class,
            'PUT',
            '',
            '',
            false,
            'update',
            [['name' => 'params', 'type' => 'array']],
            [],
            UpdateRequest::class,
            ['params' => 'setParams'],
            ['params' => ['allowsNull' => false, 'type' => 'array']]
        );
        $responseData = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32601,
                'message' => 'Method not found. Additional info: '
            ],
            'id' => '1'
        ];
        [$serviceLocator, $request, $methodSpecCollection, $validator, $container] = $this->prepare2($data, $methodSpec);

        $security = $this->createMock(Security::class);
        $security
            ->expects($this->any())
            ->method('isGranted')
            ->willReturn(true);

        $controller = new ApiController(['*'], $security);
        $controller->setContainer($serviceLocator);

        $result = $controller->index($request, $methodSpecCollection, $validator, $container);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }

    private function prepare2(array $data, MethodSpec $methodSpec)
    {
        $request = $this->createMock(Request::class);
        $request->request = new InputBag([]);
        $request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('POST');

        $request
            ->expects($this->once())
            ->method('getPathInfo')
            ->willReturn('/api/v1');
        $request
            ->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode($data, JSON_UNESCAPED_UNICODE));

        $annotationReader = new AnnotationReader();
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
            throw new Exception('Could not define method name');
        }

        $methodSpecCollection = new MethodSpecCollection();
        $methodSpecCollection->addMethodSpec(1, $methodName, $methodSpec);

        $validator = $this->createMock(ValidatorInterface::class);

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator
            ->expects($this->once())
            ->method('has')
            ->willReturn(true);

        $jsonEncoder = new JsonEncoder();
        $normalizer = new TraceableNormalizer(new ObjectNormalizer(), new SerializerDataCollector());
        $serializer = new Serializer(normalizers: [$normalizer], encoders: [$jsonEncoder]);

        $serviceLocator
            ->expects($this->once())
            ->method('get')
            ->willReturn($serializer);

        $container = $this->createMock(Container::class);

        return [$serviceLocator, $request, $methodSpecCollection, $validator, $container];
    }

    public function testRpcCallWithInvalidJson()
    {
        $data = '{"jsonrpc": "2.0", "method": "foobar, "params": "bar", "baz]';
        $methodSpec = new MethodSpec(
            UpdateMethod::class,
            'PUT',
            '',
            '',
            false,
            'update',
            [['name' => 'params', 'type' => 'array']],
            [],
            UpdateRequest::class,
            ['params' => 'setParams'],
            ['params' => ['allowsNull' => false, 'type' => 'array']]
        );
        $responseData = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32700,
                'message' => 'Parse error. Additional info: '
            ],
            //'id' => null //todo тот параметр сейчас не пробрасывается из-за настроек нормалайзера - он все null значения чистит
        ];
        [$serviceLocator, $request, $methodSpecCollection, $validator, $container] = $this->prepare3($data, $methodSpec);

        $security = $this->createMock(Security::class);
        $security
            ->expects($this->any())
            ->method('isGranted')
            ->willReturn(true);

        $controller = new ApiController(['*'], $security);
        $controller->setContainer($serviceLocator);

        $result = $controller->index($request, $methodSpecCollection, $validator, $container);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }

    private function prepare3(string $data, MethodSpec $methodSpec)
    {
        $request = $this->createMock(Request::class);
        $request->request = new InputBag([]);
        $request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('POST');

        $request
            ->expects($this->once())
            ->method('getPathInfo')
            ->willReturn('/api/v1');
        $request
            ->expects($this->once())
            ->method('getContent')
            ->willReturn($data);

        $annotationReader = new AnnotationReader();
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
            throw new Exception('Could not define method name');
        }

        $methodSpecCollection = new MethodSpecCollection();
        $methodSpecCollection->addMethodSpec(1, $methodName, $methodSpec);

        $validator = $this->createMock(ValidatorInterface::class);

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator
            ->expects($this->once())
            ->method('has')
            ->willReturn(true);

        $jsonEncoder = new JsonEncoder();
        $normalizer = new TraceableNormalizer(new ObjectNormalizer(), new SerializerDataCollector());
        $serializer = new Serializer(normalizers: [$normalizer], encoders: [$jsonEncoder]);

        $serviceLocator
            ->expects($this->once())
            ->method('get')
            ->willReturn($serializer);

        $container = $this->createMock(Container::class);

        return [$serviceLocator, $request, $methodSpecCollection, $validator, $container];
    }

    public function testRpcCallWithInvalidRequestObject()
    {
        $data = '{"jsonrpc": "2.0", "method": 1, "params": "bar"}';
        $methodSpec = new MethodSpec(
            UpdateMethod::class,
            'PUT',
            '',
            '',
            false,
            'update',
            [['name' => 'params', 'type' => 'array']],
            [],
            UpdateRequest::class,
            ['params' => 'setParams'],
            ['params' => ['allowsNull' => false, 'type' => 'array']]
        );
        $responseData = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32600,
                'message' => 'Invalid Request. Additional info: '
            ],
            //'id' => null //todo тот параметр сейчас не пробрасывается из-за настроек нормалайзера - он все null значения чистит
        ];
        [$serviceLocator, $request, $methodSpecCollection, $validator, $container] = $this->prepare4($data, $methodSpec);

        $security = $this->createMock(Security::class);
        $security
            ->expects($this->any())
            ->method('isGranted')
            ->willReturn(true);

        $controller = new ApiController(['*'], $security);
        $controller->setContainer($serviceLocator);

        $result = $controller->index($request, $methodSpecCollection, $validator, $container);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }

    private function prepare4(string $data, MethodSpec $methodSpec)
    {
        $request = $this->createMock(Request::class);
        $request->request = new InputBag([]);
        $request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('POST');

        $request
            ->expects($this->once())
            ->method('getPathInfo')
            ->willReturn('/api/v1');
        $request
            ->expects($this->once())
            ->method('getContent')
            ->willReturn($data);

        $annotationReader = new AnnotationReader();
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
            throw new Exception('Could not define method name');
        }

        $methodSpecCollection = new MethodSpecCollection();
        $methodSpecCollection->addMethodSpec(1, $methodName, $methodSpec);

        $validator = $this->createMock(ValidatorInterface::class);

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator
            ->expects($this->once())
            ->method('has')
            ->willReturn(true);

        $jsonEncoder = new JsonEncoder();
        $normalizer = new TraceableNormalizer(new ObjectNormalizer(), new SerializerDataCollector());
        $serializer = new Serializer(normalizers: [$normalizer], encoders: [$jsonEncoder]);

        $serviceLocator
            ->expects($this->once())
            ->method('get')
            ->willReturn($serializer);

        $container = $this->createMock(Container::class);

        return [$serviceLocator, $request, $methodSpecCollection, $validator, $container];
    }

    public function testRpcCallBatchWithInvalidJson()
    {
        $data = '[{"jsonrpc": "2.0", "method": "sum", "params": [1,2,4], "id": "1"},{"jsonrpc": "2.0", "method"]';
        $methodSpec = new MethodSpec(
            UpdateMethod::class,
            'PUT',
            '',
            '',
            false,
            'update',
            [['name' => 'params', 'type' => 'array']],
            [],
            UpdateRequest::class,
            ['params' => 'setParams'],
            ['params' => ['allowsNull' => false, 'type' => 'array']]
        );
        $responseData = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32700,
                'message' => 'Parse error. Additional info: '
            ],
            //'id' => null //todo тот параметр сейчас не пробрасывается из-за настроек нормалайзера - он все null значения чистит
        ];
        [$serviceLocator, $request, $methodSpecCollection, $validator, $container] = $this->prepare5($data, $methodSpec);

        $security = $this->createMock(Security::class);
        $security
            ->expects($this->any())
            ->method('isGranted')
            ->willReturn(true);

        $controller = new ApiController(['*'], $security);
        $controller->setContainer($serviceLocator);

        $result = $controller->index($request, $methodSpecCollection, $validator, $container);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }

    private function prepare5(string $data, MethodSpec $methodSpec)
    {
        $request = $this->createMock(Request::class);
        $request->request = new InputBag([]);
        $request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('POST');

        $request
            ->expects($this->once())
            ->method('getPathInfo')
            ->willReturn('/api/v1');
        $request
            ->expects($this->once())
            ->method('getContent')
            ->willReturn($data);

        $annotationReader = new AnnotationReader();
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
            throw new Exception('Could not define method name');
        }

        $methodSpecCollection = new MethodSpecCollection();
        $methodSpecCollection->addMethodSpec(1, $methodName, $methodSpec);

        $validator = $this->createMock(ValidatorInterface::class);

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator
            ->expects($this->once())
            ->method('has')
            ->willReturn(true);

        $jsonEncoder = new JsonEncoder();
        $normalizer = new TraceableNormalizer(new ObjectNormalizer(), new SerializerDataCollector());
        $serializer = new Serializer(normalizers: [$normalizer], encoders: [$jsonEncoder]);

        $serviceLocator
            ->expects($this->once())
            ->method('get')
            ->willReturn($serializer);

        $container = $this->createMock(Container::class);

        return [$serviceLocator, $request, $methodSpecCollection, $validator, $container];
    }

    public function testRpcCallWithAnEmptyArray()
    {
        $data = '[]';
        $methodSpec = new MethodSpec(
            UpdateMethod::class,
            'PUT',
            '',
            '',
            false,
            'update',
            [['name' => 'params', 'type' => 'array']],
            [],
            UpdateRequest::class,
            ['params' => 'setParams'],
            ['params' => ['allowsNull' => false, 'type' => 'array']]
        );
        $responseData = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32600,
                'message' => 'Invalid Request. Additional info: '
            ],
            //'id' => null //todo тот параметр сейчас не пробрасывается из-за настроек нормалайзера - он все null значения чистит
        ];
        [$serviceLocator, $request, $methodSpecCollection, $validator, $container] = $this->prepare6($data, $methodSpec);

        $security = $this->createMock(Security::class);
        $security
            ->expects($this->any())
            ->method('isGranted')
            ->willReturn(true);

        $controller = new ApiController(['*'], $security);
        $controller->setContainer($serviceLocator);

        $result = $controller->index($request, $methodSpecCollection, $validator, $container);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }

    private function prepare6(string $data, MethodSpec $methodSpec)
    {
        $request = $this->createMock(Request::class);
        $request->request = new InputBag([]);
        $request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('POST');

        $request
            ->expects($this->once())
            ->method('getPathInfo')
            ->willReturn('/api/v1');
        $request
            ->expects($this->once())
            ->method('getContent')
            ->willReturn($data);

        $annotationReader = new AnnotationReader();
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
            throw new Exception('Could not define method name');
        }

        $methodSpecCollection = new MethodSpecCollection();
        $methodSpecCollection->addMethodSpec(1, $methodName, $methodSpec);

        $validator = $this->createMock(ValidatorInterface::class);

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator
            ->expects($this->once())
            ->method('has')
            ->willReturn(true);

        $jsonEncoder = new JsonEncoder();
        $normalizer = new TraceableNormalizer(new ObjectNormalizer(), new SerializerDataCollector());
        $serializer = new Serializer(normalizers: [$normalizer], encoders: [$jsonEncoder]);

        $serviceLocator
            ->expects($this->once())
            ->method('get')
            ->willReturn($serializer);

        $container = $this->createMock(Container::class);

        return [$serviceLocator, $request, $methodSpecCollection, $validator, $container];
    }

    public function testRpcCallWithAnInvalidBatchButNotEmpty()
    {
        $data = '[1]';
        $methodSpec = new MethodSpec(
            UpdateMethod::class,
            'PUT',
            '',
            '',
            false,
            'update',
            [['name' => 'params', 'type' => 'array']],
            [],
            UpdateRequest::class,
            ['params' => 'setParams'],
            ['params' => ['allowsNull' => false, 'type' => 'array']]
        );
        $responseData = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32600,
                'message' => 'Invalid Request. Additional info: '
            ],
            //'id' => null //todo тот параметр сейчас не пробрасывается из-за настроек нормалайзера - он все null значения чистит
        ];
        [$serviceLocator, $request, $methodSpecCollection, $validator, $container] = $this->prepare7($data, $methodSpec);

        $security = $this->createMock(Security::class);
        $security
            ->expects($this->any())
            ->method('isGranted')
            ->willReturn(true);

        $controller = new ApiController(['*'], $security);
        $controller->setContainer($serviceLocator);

        $result = $controller->index($request, $methodSpecCollection, $validator, $container);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }

    private function prepare7(string $data, MethodSpec $methodSpec)
    {
        $request = $this->createMock(Request::class);
        $request->request = new InputBag([]);
        $request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('POST');

        $request
            ->expects($this->once())
            ->method('getPathInfo')
            ->willReturn('/api/v1');
        $request
            ->expects($this->once())
            ->method('getContent')
            ->willReturn($data);

        $annotationReader = new AnnotationReader();
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
            throw new Exception('Could not define method name');
        }

        $methodSpecCollection = new MethodSpecCollection();
        $methodSpecCollection->addMethodSpec(1, $methodName, $methodSpec);

        $validator = $this->createMock(ValidatorInterface::class);

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator
            ->expects($this->once())
            ->method('has')
            ->willReturn(true);

        $jsonEncoder = new JsonEncoder();
        $normalizer = new TraceableNormalizer(new ObjectNormalizer(), new SerializerDataCollector());
        $serializer = new Serializer(normalizers: [$normalizer], encoders: [$jsonEncoder]);

        $serviceLocator
            ->expects($this->once())
            ->method('get')
            ->willReturn($serializer);

        $container = $this->createMock(Container::class);

        return [$serviceLocator, $request, $methodSpecCollection, $validator, $container];
    }

    public function testRpcCallWithAnInvalidBatch()
    {
        $data = '[1, 2, 3]';
        $methodSpec = new MethodSpec(
            UpdateMethod::class,
            'PUT',
            '',
            '',
            false,
            'update',
            [['name' => 'params', 'type' => 'array']],
            [],
            UpdateRequest::class,
            ['params' => 'setParams'],
            ['params' => ['allowsNull' => false, 'type' => 'array']]
        );
        $responseData = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32600,
                'message' => 'Invalid Request. Additional info: '
            ],
            //'id' => null //todo тот параметр сейчас не пробрасывается из-за настроек нормалайзера - он все null значения чистит
        ];
        [$serviceLocator, $request, $methodSpecCollection, $validator, $container] = $this->prepare8($data, $methodSpec);

        $security = $this->createMock(Security::class);
        $security
            ->expects($this->any())
            ->method('isGranted')
            ->willReturn(true);

        $controller = new ApiController(['*'], $security);
        $controller->setContainer($serviceLocator);

        $result = $controller->index($request, $methodSpecCollection, $validator, $container);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }

    private function prepare8(string $data, MethodSpec $methodSpec)
    {
        $request = $this->createMock(Request::class);
        $request->request = new InputBag([]);
        $request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('POST');

        $request
            ->expects($this->once())
            ->method('getPathInfo')
            ->willReturn('/api/v1');
        $request
            ->expects($this->once())
            ->method('getContent')
            ->willReturn($data);

        $annotationReader = new AnnotationReader();
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
            throw new Exception('Could not define method name');
        }

        $methodSpecCollection = new MethodSpecCollection();
        $methodSpecCollection->addMethodSpec(1, $methodName, $methodSpec);

        $validator = $this->createMock(ValidatorInterface::class);

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator
            ->expects($this->once())
            ->method('has')
            ->willReturn(true);

        $jsonEncoder = new JsonEncoder();
        $normalizer = new TraceableNormalizer(new ObjectNormalizer(), new SerializerDataCollector());
        $serializer = new Serializer(normalizers: [$normalizer], encoders: [$jsonEncoder]);

        $serviceLocator
            ->expects($this->once())
            ->method('get')
            ->willReturn($serializer);

        $container = $this->createMock(Container::class);

        return [$serviceLocator, $request, $methodSpecCollection, $validator, $container];
    }

    public function testRpcCallBatch()
    {
        $data = [
            [
                'jsonrpc' => '2.0',
                'method' => 'sum',
                'params' => [1, 2, 4],
                'id' => '1',
            ],
            [
                'jsonrpc' => '2.0',
                'method' => 'notify_hello',
                'params' => [7],
            ],
            [
                'jsonrpc' => '2.0',
                'method' => 'subtract',
                'params' => [42, 23],
                'id' => '2',
            ],
            [
                'foo' => 'boo',
            ],
            [
                'jsonrpc' => '2.0',
                'method' => 'foo.get',
                'params' => ['name', 'myself'],
                'id' => '5',
            ],
            [
                'jsonrpc' => '2.0',
                'method' => 'get_data',
                'id' => '9',
            ],
        ];
        $methodSpecs = [
            new MethodSpec(
                SumMethod::class,
                'POST',
                '',
                '',
                false,
                'sum',
                [['name' => 'params', 'type' => 'array']],
                [],
                SumRequest::class,
                ['params' => 'setParams'],
                ['params' => ['allowsNull' => false, 'type' => 'array']]
            ),
            new MethodSpec(
                NotifyHelloMethod::class,
                'POST',
                '',
                '',
                false,
                'notify_hello',
                [['name' => 'params', 'type' => 'array']],
                [],
                NotifyHelloRequest::class,
                ['params' => 'setParams'],
                ['params' => ['allowsNull' => false, 'type' => 'array']]
            ),
            new MethodSpec(
                SubtractMethod::class,
                'POST',
                '',
                '',
                false,
                'subtract',
                [['name' => 'params', 'type' => 'array']],
                [],
                SubtractRequest::class,
                ['params' => 'setParams'],
                ['params' => ['allowsNull' => false, 'type' => 'array']]
            ),
            new MethodSpec(
                GetDataMethod::class,
                'POST',
                '',
                '',
                false,
                'get_data',
                [],
                [],
                GetDataRequest::class,
                [],
                []
            ),
        ];
        $responseData = [
            [
                'jsonrpc' => '2.0',
                'result' => 7,
                'id' => '1',
            ],
            [
                'jsonrpc' => '2.0',
                'result' => 19,
                'id' => '2',
            ],
            [
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid Request. Additional info: '
                ],
            ],
            [
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32601,
                    'message' => 'Method not found. Additional info: '
                ],
                'id' => '5'
            ],
            [
                'jsonrpc' => '2.0',
                'result' => ['hello', 5],
                'id' => '9',
            ],
        ];
        [$serviceLocator, $request, $methodSpecCollection, $validator, $container] = $this->prepare9($data, $methodSpecs);

        $security = $this->createMock(Security::class);
        $security
            ->expects($this->any())
            ->method('isGranted')
            ->willReturn(true);

        $controller = new ApiController(['*'], $security);
        $controller->setContainer($serviceLocator);

        $result = $controller->index($request, $methodSpecCollection, $validator, $container);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }

    public function testRpcCallBatchAllNotifications()
    {
        $data = [
            [
                'jsonrpc' => '2.0',
                'method' => 'notify_sum',
                'params' => [1, 2, 4],
            ],
            [
                'jsonrpc' => '2.0',
                'method' => 'notify_hello',
                'params' => [7],
            ],
        ];
        $methodSpecs = [
            new MethodSpec(
                NotifySumMethod::class,
                'POST',
                '',
                '',
                false,
                'notify_sum',
                [['name' => 'params', 'type' => 'array']],
                [],
                NotifySumRequest::class,
                ['params' => 'setParams'],
                ['params' => ['allowsNull' => false, 'type' => 'array']]
            ),
            new MethodSpec(
                NotifyHelloMethod::class,
                'POST',
                '',
                '',
                false,
                'notify_hello',
                [['name' => 'params', 'type' => 'array']],
                [],
                NotifyHelloRequest::class,
                ['params' => 'setParams'],
                ['params' => ['allowsNull' => false, 'type' => 'array']]
            ),
        ];
        $responseData = '{}';
        [$serviceLocator, $request, $methodSpecCollection, $validator, $container] = $this->prepare9($data, $methodSpecs);

        $security = $this->createMock(Security::class);
        $security
            ->expects($this->any())
            ->method('isGranted')
            ->willReturn(true);

        $controller = new ApiController(['*'], $security);
        $controller->setContainer($serviceLocator);

        $result = $controller->index($request, $methodSpecCollection, $validator, $container);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals($responseData, $result->getContent());
    }

    private function prepare9(array $data, array $methodSpecs)
    {
        $request = $this->createMock(Request::class);
        $request->request = new InputBag([]);
        $request
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn('POST');

        $request
            ->expects($this->once())
            ->method('getPathInfo')
            ->willReturn('/api/v1');
        $request
            ->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode($data, JSON_UNESCAPED_UNICODE));

        $annotationReader = new AnnotationReader();
        $methodSpecCollection = new MethodSpecCollection();

        $container = $this->createMock(Container::class);
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
                throw new Exception('Could not define method name');
            }
            $methodSpecCollection->addMethodSpec(1, $methodName, $methodSpec);
            $container
                ->expects($this->any())
                ->method('get')
                ->willReturnCallback(function ($class) {
                    return new $class;
                });
        }

        $validator = $this->createMock(ValidatorInterface::class);
        $violations = new ConstraintViolationList();
        $validator
            ->expects($this->atLeastOnce())
            ->method('validate')
            ->willReturn($violations);

        $serviceLocator = $this->createMock(ServiceLocator::class);
        $serviceLocator
            ->expects($this->any())
            ->method('has')
            ->willReturn(true);

        $jsonEncoder = new JsonEncoder();
        $normalizer = new TraceableNormalizer(new ObjectNormalizer(), new SerializerDataCollector());
        $serializer = new Serializer(normalizers: [$normalizer], encoders: [$jsonEncoder]);

        $serviceLocator
            ->expects($this->any())
            ->method('get')
            ->willReturn($serializer);

        return [$serviceLocator, $request, $methodSpecCollection, $validator, $container];
    }
}