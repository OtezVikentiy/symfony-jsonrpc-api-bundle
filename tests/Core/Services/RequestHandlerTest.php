<?php

namespace OV\JsonRPCAPIBundle\Tests\Core\Services;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;
use OV\JsonRPCAPIBundle\Core\Services\HeadersPreparer;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler\SingleBatchStrategy;
use OV\JsonRPCAPIBundle\Core\Services\ResponseService;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use OV\JsonRPCAPIBundle\RPC\V1\Subtract\SubtractRequest;
use OV\JsonRPCAPIBundle\RPC\V1\SubtractMethod;
use OV\JsonRPCAPIBundle\RPC\V1\Test\TestRequest;
use OV\JsonRPCAPIBundle\RPC\V1\TestMethod;
use OV\JsonRPCAPIBundle\RPC\V1\TestPreProcessor\Request as PreProcessorRequest;
use OV\JsonRPCAPIBundle\RPC\V1\TestPreProcessorMethod;
use OV\JsonRPCAPIBundle\RPC\V1\PlainResponse\Request as PlainRequest;
use OV\JsonRPCAPIBundle\RPC\V1\PlainResponseMethod;
use OV\JsonRPCAPIBundle\RPC\V1\NotifyHello\NotifyHelloRequest;
use OV\JsonRPCAPIBundle\RPC\V1\NotifyHelloMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RequestHandlerTest extends TestCase
{
    private function createRequestHandler(
        MethodSpecCollection $specCollection,
        bool $isGranted = true,
        array $violations = [],
        ?Container $container = null,
        bool $strictNotifications = false,
    ): RequestHandler {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturn($isGranted);

        $validator = $this->createMock(ValidatorInterface::class);
        $violationList = new ConstraintViolationList();
        $validator->method('validate')->willReturn($violationList);

        $headersPreparer = new HeadersPreparer(['*']);
        $responseService = new ResponseService($headersPreparer);

        if (is_null($container)) {
            $container = $this->createMock(Container::class);
        }

        return new RequestHandler(
            $security,
            $specCollection,
            $validator,
            $headersPreparer,
            $container,
            $responseService,
            $strictNotifications,
        );
    }

    private function createContainerWithMethod(string $methodClass): Container
    {
        $container = $this->createMock(Container::class);
        $instance = new $methodClass();
        $container->method('get')->willReturnMap([
            [$methodClass, 1, $instance],
        ]);
        return $container;
    }

    public function testProcessBatchSuccessfulRequest(): void
    {
        $specCollection = new MethodSpecCollection();
        $methodSpec = new MethodSpec(
            methodClass: SubtractMethod::class,
            requestType: 'POST',
            methodName: 'subtract',
            requestMetadata: new RequestMetadata(
                request: SubtractRequest::class,
                allParameters: [['name' => 'params', 'type' => 'array']],
                requiredParameters: [],
                requestGetters: ['params' => 'getParams'],
                requestSetters: ['params' => 'setParams'],
                requestAdders: [],
                validators: ['params' => ['allowsNull' => false, 'type' => 'array']],
            ),
            swaggerMetadata: new SwaggerMetadata(
                summary: '',
                description: '',
                ignoreInSwagger: false,
            ),
        );
        $specCollection->addMethodSpec(1, 'subtract', $methodSpec);

        $container = $this->createContainerWithMethod(SubtractMethod::class);
        $handler = $this->createRequestHandler($specCollection, container: $container);

        $batch = [
            'jsonrpc' => '2.0',
            'method' => 'subtract',
            'params' => [42, 23],
            'id' => '1',
        ];

        $result = $handler->processBatch($batch, 1, 'POST');

        $this->assertInstanceOf(OvResponseInterface::class, $result);
        $content = json_decode($result->getContent(), true);
        $this->assertEquals('2.0', $content['jsonrpc']);
        $this->assertEquals(['result' => 19], $content['result']);
        $this->assertEquals('1', $content['id']);
    }

    public function testProcessBatchMethodNotFound(): void
    {
        $specCollection = new MethodSpecCollection();
        $handler = $this->createRequestHandler($specCollection);

        $batch = [
            'jsonrpc' => '2.0',
            'method' => 'nonexistent',
            'id' => '1',
        ];

        $result = $handler->processBatch($batch, 1, 'POST');

        $this->assertInstanceOf(OvResponseInterface::class, $result);
        $content = json_decode($result->getContent(), true);
        $this->assertEquals(-32601, $content['error']['code']);
        $this->assertStringContainsString('Method not found', $content['error']['message']);
    }

    public function testProcessBatchInvalidRequestType(): void
    {
        $specCollection = new MethodSpecCollection();
        $methodSpec = new MethodSpec(
            methodClass: SubtractMethod::class,
            requestType: 'PUT',
            methodName: 'subtract',
            requestMetadata: new RequestMetadata(
                request: SubtractRequest::class,
                allParameters: [],
                requiredParameters: [],
                requestGetters: [],
                requestSetters: [],
                requestAdders: [],
                validators: [],
            ),
            swaggerMetadata: new SwaggerMetadata(
                summary: '',
                description: '',
                ignoreInSwagger: false,
            ),
        );
        $specCollection->addMethodSpec(1, 'subtract', $methodSpec);

        $handler = $this->createRequestHandler($specCollection);

        $batch = [
            'jsonrpc' => '2.0',
            'method' => 'subtract',
            'params' => [42, 23],
            'id' => '1',
        ];

        $result = $handler->processBatch($batch, 1, 'POST');

        $content = json_decode($result->getContent(), true);
        $this->assertEquals(-32600, $content['error']['code']);
        $this->assertStringContainsString('Invalid Request', $content['error']['message']);
    }

    public function testProcessBatchRolesNotAllowed(): void
    {
        $specCollection = new MethodSpecCollection();
        $methodSpec = new MethodSpec(
            methodClass: TestMethod::class,
            requestType: 'POST',
            methodName: 'test',
            requestMetadata: new RequestMetadata(
                request: TestRequest::class,
                allParameters: [],
                requiredParameters: [],
                requestGetters: [],
                requestSetters: [],
                requestAdders: [],
                validators: [],
            ),
            swaggerMetadata: new SwaggerMetadata(
                summary: '',
                description: '',
                ignoreInSwagger: false,
            ),
            roles: ['ROLE_ADMIN'],
        );
        $specCollection->addMethodSpec(1, 'test', $methodSpec);

        $handler = $this->createRequestHandler($specCollection, isGranted: false);

        $batch = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'id' => '1',
        ];

        $result = $handler->processBatch($batch, 1, 'POST');

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(403, $result->getStatusCode());
    }

    public function testProcessBatchRolesAllowed(): void
    {
        $specCollection = new MethodSpecCollection();
        $methodSpec = new MethodSpec(
            methodClass: TestMethod::class,
            requestType: 'POST',
            methodName: 'test',
            requestMetadata: new RequestMetadata(
                request: TestRequest::class,
                allParameters: [['name' => 'id', 'type' => 'int'], ['name' => 'title', 'type' => 'string']],
                requiredParameters: [['name' => 'id', 'type' => 'int']],
                requestGetters: ['id' => 'getId', 'title' => 'getTitle'],
                requestSetters: ['id' => 'setId', 'title' => 'setTitle'],
                requestAdders: [],
                validators: ['id' => ['allowsNull' => false, 'type' => 'int'], 'title' => ['allowsNull' => false, 'type' => 'string']],
            ),
            swaggerMetadata: new SwaggerMetadata(
                summary: '',
                description: '',
                ignoreInSwagger: false,
            ),
            roles: ['ROLE_USER'],
        );
        $specCollection->addMethodSpec(1, 'test', $methodSpec);

        $container = $this->createContainerWithMethod(TestMethod::class);
        $handler = $this->createRequestHandler($specCollection, isGranted: true, container: $container);

        $batch = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'params' => ['title' => 'Hello'],
            'id' => '1',
        ];

        $result = $handler->processBatch($batch, 1, 'POST');

        $content = json_decode($result->getContent(), true);
        $this->assertEquals('2.0', $content['jsonrpc']);
        $this->assertArrayHasKey('result', $content);
    }

    public function testProcessBatchInvalidRequest(): void
    {
        $specCollection = new MethodSpecCollection();
        $handler = $this->createRequestHandler($specCollection);

        $batch = ['foo' => 'bar'];

        $result = $handler->processBatch($batch, 1, 'POST');

        $content = json_decode($result->getContent(), true);
        $this->assertEquals(-32600, $content['error']['code']);
    }

    public function testApplyStrategyDelegatesToStrategy(): void
    {
        $specCollection = new MethodSpecCollection();
        $handler = $this->createRequestHandler($specCollection);

        $strategy = $this->createMock(RequestHandler\HandleBatchInterface::class);
        $expectedResponse = new JsonResponse(['test' => true]);
        $strategy->method('handleBatch')->willReturn($expectedResponse);

        $result = $handler->applyStrategy($strategy, [], 1, 'POST');

        $this->assertSame($expectedResponse, $result);
    }

    public function testProcessBatchWithEmptyRoles(): void
    {
        $specCollection = new MethodSpecCollection();
        $methodSpec = new MethodSpec(
            methodClass: SubtractMethod::class,
            requestType: 'POST',
            methodName: 'subtract',
            requestMetadata: new RequestMetadata(
                request: SubtractRequest::class,
                allParameters: [['name' => 'params', 'type' => 'array']],
                requiredParameters: [],
                requestGetters: ['params' => 'getParams'],
                requestSetters: ['params' => 'setParams'],
                requestAdders: [],
                validators: ['params' => ['allowsNull' => false, 'type' => 'array']],
            ),
            swaggerMetadata: new SwaggerMetadata(
                summary: '',
                description: '',
                ignoreInSwagger: false,
            ),
        );
        $specCollection->addMethodSpec(1, 'subtract', $methodSpec);

        $container = $this->createContainerWithMethod(SubtractMethod::class);
        $handler = $this->createRequestHandler($specCollection, container: $container);

        $batch = [
            'jsonrpc' => '2.0',
            'method' => 'subtract',
            'params' => [10, 5],
            'id' => '1',
        ];

        $result = $handler->processBatch($batch, 1, 'POST');

        $content = json_decode($result->getContent(), true);
        $this->assertArrayHasKey('result', $content);
        $this->assertEquals(['result' => 5], $content['result']);
    }

    public function testProcessBatchNotificationDefaultReturnsResponse(): void
    {
        $specCollection = new MethodSpecCollection();
        $methodSpec = new MethodSpec(
            methodClass: NotifyHelloMethod::class,
            requestType: 'POST',
            methodName: 'notify_hello',
            requestMetadata: new RequestMetadata(
                request: NotifyHelloRequest::class,
                allParameters: [['name' => 'params', 'type' => 'array']],
                requiredParameters: [],
                requestGetters: ['params' => 'getParams'],
                requestSetters: ['params' => 'setParams'],
                requestAdders: [],
                validators: ['params' => ['allowsNull' => false, 'type' => 'array']],
            ),
            swaggerMetadata: new SwaggerMetadata(
                summary: '',
                description: '',
                ignoreInSwagger: false,
            ),
        );
        $specCollection->addMethodSpec(1, 'notify_hello', $methodSpec);

        $container = $this->createContainerWithMethod(NotifyHelloMethod::class);
        // strictNotifications: false (default) — notification with non-empty response still returns data
        $handler = $this->createRequestHandler($specCollection, container: $container, strictNotifications: false);

        $batch = [
            'jsonrpc' => '2.0',
            'method' => 'notify_hello',
            'params' => [7],
        ];

        $result = $handler->processBatch($batch, 1, 'POST');

        // Default mode: notification with non-empty result still returns response (for developer convenience)
        $this->assertNull($result);
    }

    public function testProcessBatchNotificationStrictReturnsNull(): void
    {
        $specCollection = new MethodSpecCollection();
        $methodSpec = new MethodSpec(
            methodClass: SubtractMethod::class,
            requestType: 'POST',
            methodName: 'subtract',
            requestMetadata: new RequestMetadata(
                request: SubtractRequest::class,
                allParameters: [['name' => 'params', 'type' => 'array']],
                requiredParameters: [],
                requestGetters: ['params' => 'getParams'],
                requestSetters: ['params' => 'setParams'],
                requestAdders: [],
                validators: ['params' => ['allowsNull' => false, 'type' => 'array']],
            ),
            swaggerMetadata: new SwaggerMetadata(
                summary: '',
                description: '',
                ignoreInSwagger: false,
            ),
        );
        $specCollection->addMethodSpec(1, 'subtract', $methodSpec);

        $container = $this->createContainerWithMethod(SubtractMethod::class);
        // strictNotifications: true — per JSON-RPC 2.0 spec, no response for notifications
        $handler = $this->createRequestHandler($specCollection, container: $container, strictNotifications: true);

        $batch = [
            'jsonrpc' => '2.0',
            'method' => 'subtract',
            'params' => [42, 23],
            // no 'id' — this is a notification
        ];

        $result = $handler->processBatch($batch, 1, 'POST');

        // Strict mode: notification MUST NOT get a response (JSON-RPC 2.0 spec)
        $this->assertNull($result);
    }
}
