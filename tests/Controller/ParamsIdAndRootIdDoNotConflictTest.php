<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\RPC\V1\Test\TestRequest;
use OV\JsonRPCAPIBundle\RPC\V1\TestMethod;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ParamsIdAndRootIdDoNotConflictTest extends AbstractTest
{
    public function testRootIdInResponseAndParamsIdInBusinessLogic()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'params' => [
                'id' => 10,
                'title' => 'hello',
            ],
            'id' => '99',
        ];

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
        );

        $responseData = [
            'jsonrpc' => '2.0',
            'result' => [
                'success' => true,
                'title' => 'hello',
                'request' => null,
                'tests' => [],
                'errors' => [],
            ],
            'id' => '99',
        ];

        $result = $this->executeControllerTest($data, $methodSpec);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }

    public function testParamsIdIsNotOverriddenByRootId()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'params' => [
                'id' => 5,
                'title' => 'world',
            ],
            'id' => '42',
        ];

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
        );

        $responseData = [
            'jsonrpc' => '2.0',
            'result' => [
                'success' => true,
                'title' => 'world',
                'request' => null,
                'tests' => [],
                'errors' => [],
            ],
            'id' => '42',
        ];

        $result = $this->executeControllerTest($data, $methodSpec);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }
}
