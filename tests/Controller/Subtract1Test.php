<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\RPC\V1\Subtract\SubtractRequest;
use OV\JsonRPCAPIBundle\RPC\V1\SubtractMethod;
use Symfony\Component\HttpFoundation\JsonResponse;

final class Subtract1Test extends AbstractTest
{
    public function testRpcCallWithPositionalParameters()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'subtract',
            'params' => [42, 23],
            'id' => '2',
        ];

        $methodSpec = new MethodSpec(
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
        );

        $responseData = [
            'jsonrpc' => '2.0',
            'result' => 19,
            'id' => '2',
        ];

        $result = $this->executeControllerTest($data, $methodSpec);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }
}