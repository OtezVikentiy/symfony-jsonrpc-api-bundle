<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\RPC\V1\Nested\Multiply\MultiplyRequest;
use OV\JsonRPCAPIBundle\RPC\V1\Nested\MultiplyMethod;
use Symfony\Component\HttpFoundation\JsonResponse;

final class NestedNamespaceMethodTest extends AbstractTest
{
    public function testRpcCallWithMethodInNestedNamespace()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'multiply',
            'params' => [6, 7],
            'id' => '1',
        ];

        $methodSpec = new MethodSpec(
            methodClass: MultiplyMethod::class,
            requestType: 'POST',
            methodName: 'multiply',
            requestMetadata: new RequestMetadata(
                request: MultiplyRequest::class,
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

        $responseData = [
            'jsonrpc' => '2.0',
            'result' => ['result' => 42],
            'id' => '1',
        ];

        $result = $this->executeControllerTest($data, $methodSpec);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }
}
