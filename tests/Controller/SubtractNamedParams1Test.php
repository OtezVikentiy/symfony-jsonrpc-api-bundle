<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\RPC\V1\Subtract2\Subtract2Request;
use OV\JsonRPCAPIBundle\RPC\V1\Subtract2Method;
use Symfony\Component\HttpFoundation\JsonResponse;

final class SubtractNamedParams1Test extends AbstractTest
{
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
            methodClass: Subtract2Method::class,
            requestType: 'POST',
            summary: '',
            description: '',
            ignoreInSwagger: false,
            methodName: 'subtract2',
            allParameters: [['name' => 'subtrahend', 'type' => 'int'], ['name' => 'minuend', 'type' => 'int']],
            requiredParameters: [],
            request: Subtract2Request::class,
            requestSetters: ['subtrahend' => 'setSubtrahend', 'minuend' => 'setMinuend'],
            validators: ['subtrahend' => ['allowsNull' => false, 'type' => 'int'], 'minuend' => ['allowsNull' => false, 'type' => 'int']]
        );

        $responseData = [
            'jsonrpc' => '2.0',
            'result' => 19,
            'id' => '4',
        ];

        $result = $this->executeControllerTest($data, $methodSpec);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }
}