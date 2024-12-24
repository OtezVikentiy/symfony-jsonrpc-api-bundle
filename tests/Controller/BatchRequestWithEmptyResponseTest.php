<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\RPC\V1\NotifyHello\NotifyHelloRequest;
use OV\JsonRPCAPIBundle\RPC\V1\NotifyHelloMethod;
use OV\JsonRPCAPIBundle\RPC\V1\NotifySum\NotifySumRequest;
use OV\JsonRPCAPIBundle\RPC\V1\NotifySumMethod;
use Symfony\Component\HttpFoundation\JsonResponse;

final class BatchRequestWithEmptyResponseTest extends AbstractTest
{
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
                methodClass: NotifySumMethod::class,
                requestType: 'POST',
                summary: '',
                description: '',
                ignoreInSwagger: false,
                methodName: 'notify_sum',
                allParameters: [['name' => 'params', 'type' => 'array']],
                requiredParameters: [],
                request: NotifySumRequest::class,
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
        ];

        $responseData = '{}';

        $result = $this->executeControllerTest(data: $data, methodSpecs: $methodSpecs);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals($responseData, $result->getContent());
    }
}