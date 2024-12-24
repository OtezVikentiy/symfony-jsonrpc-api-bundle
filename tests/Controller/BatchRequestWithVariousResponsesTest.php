<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
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
                summary: '',
                description: '',
                ignoreInSwagger: false,
                methodName: 'sum',
                allParameters: [['name' => 'params', 'type' => 'array']],
                requiredParameters: [],
                request: SumRequest::class,
                requestSetters: ['params' => 'setParams'],
                validators: ['params' => ['allowsNull' => false, 'type' => 'array']]
            ),
            new MethodSpec(
                methodClass: NotifyHelloMethod::class,
                requestType: 'POST',
                summary: '',
                description: '',
                ignoreInSwagger: false,
                methodName: 'notify_hello',
                allParameters: [['name' => 'params', 'type' => 'array']],
                requiredParameters: [],
                request: NotifyHelloRequest::class,
                requestSetters: ['params' => 'setParams'],
                validators: ['params' => ['allowsNull' => false, 'type' => 'array']]
            ),
            new MethodSpec(
                methodClass: SubtractMethod::class,
                requestType: 'POST',
                summary: '',
                description: '',
                ignoreInSwagger: false,
                methodName: 'subtract',
                allParameters: [['name' => 'params', 'type' => 'array']],
                requiredParameters: [],
                request: SubtractRequest::class,
                requestSetters: ['params' => 'setParams'],
                validators: ['params' => ['allowsNull' => false, 'type' => 'array']]
            ),
            new MethodSpec(
                methodClass: GetDataMethod::class,
                requestType: 'POST',
                summary: '',
                description: '',
                ignoreInSwagger: false,
                methodName: 'get_data',
                allParameters: [],
                requiredParameters: [],
                request: GetDataRequest::class,
                requestSetters: [],
                validators: []
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

        $result = $this->executeControllerTest(data: $data, methodSpecs: $methodSpecs);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }
}