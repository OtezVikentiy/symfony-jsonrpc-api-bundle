<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\RPC\V1\Subtract2\Subtract2Request;
use OV\JsonRPCAPIBundle\RPC\V1\Subtract2Method;
use Symfony\Component\HttpFoundation\JsonResponse;

final class SubtractNamedParams2Test extends AbstractTest
{
    public function testRpcCallWithNamedParameters()
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
            methodClass: Subtract2Method::class,
            requestType: 'POST',
            methodName: 'subtract2',
            requestMetadata: new RequestMetadata(
                request: Subtract2Request::class,
                allParameters: [['name' => 'subtrahend', 'type' => 'int'], ['name' => 'minuend', 'type' => 'int']],
                requiredParameters: [],
                requestGetters: ['subtrahend' => 'getSubtrahend', 'minuend' => 'getMinuend'],
                requestSetters: ['subtrahend' => 'setSubtrahend', 'minuend' => 'setMinuend'],
                requestAdders: [],
                validators: ['subtrahend' => ['allowsNull' => false, 'type' => 'int'], 'minuend' => ['allowsNull' => false, 'type' => 'int']],
            ),
            swaggerMetadata: new SwaggerMetadata(
                summary: '',
                description: '',
                ignoreInSwagger: false,
            ),
        );

        $responseData = [
            'jsonrpc' => '2.0',
            'result' => ['result' => 19],
            'id' => '4',
        ];

        $result = $this->executeControllerTest($data, $methodSpec);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }
}