<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\RPC\V1\GetData\GetDataRequest;
use OV\JsonRPCAPIBundle\RPC\V1\GetDataMethod;
use OV\JsonRPCAPIBundle\RPC\V1\NotifyHello\NotifyHelloRequest;
use OV\JsonRPCAPIBundle\RPC\V1\NotifyHelloMethod;
use OV\JsonRPCAPIBundle\RPC\V1\Subtract\SubtractRequest;
use OV\JsonRPCAPIBundle\RPC\V1\SubtractMethod;
use OV\JsonRPCAPIBundle\RPC\V1\Sum\SumRequest;
use OV\JsonRPCAPIBundle\RPC\V1\SumMethod;
use Symfony\Component\HttpFoundation\JsonResponse;

final class BatchRequestWithVariousResponsesTest extends AbstractTest
{
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
                methodClass: SumMethod::class,
                requestType: 'POST',
                methodName: 'sum',
                requestMetadata: new RequestMetadata(
                    request: SumRequest::class,
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
            ),
            new MethodSpec(
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
            ),
            new MethodSpec(
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
            ),
            new MethodSpec(
                methodClass: GetDataMethod::class,
                requestType: 'POST',
                methodName: 'get_data',
                requestMetadata: new RequestMetadata(
                    request: GetDataRequest::class,
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
            ),
        ];
        $responseData = [
            [
                'jsonrpc' => '2.0',
                'result' => ['result' => 7],
                'id' => '1',
            ],
            [
                'jsonrpc' => '2.0',
                'result' => ['result' => 19],
                'id' => '2',
            ],
            [
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid Request.'
                ],
                'id' => null,
            ],
            [
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32601,
                    'message' => 'Method not found.'
                ],
                'id' => '5'
            ],
            [
                'jsonrpc' => '2.0',
                'result' => ['result' => ['hello', 5]],
                'id' => '9',
            ],
        ];

        $result = $this->executeControllerTest(data: $data, methodSpecs: $methodSpecs);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }
}