<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\RPC\V1\Update\UpdateRequest;
use OV\JsonRPCAPIBundle\RPC\V1\UpdateMethod;
use Symfony\Component\HttpFoundation\JsonResponse;

final class InvalidJsonBatchRequestTest extends AbstractTest
{
    public function testRpcCallBatchWithInvalidJson()
    {
        $data = '[{"jsonrpc": "2.0", "method": "sum", "params": [1,2,4], "id": "1"},{"jsonrpc": "2.0", "method"]';

        $methodSpec = new MethodSpec(
            methodClass: UpdateMethod::class,
            requestType: 'PUT',
            methodName: 'update',
            requestMetadata: new RequestMetadata(
                request: UpdateRequest::class,
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
            'error' => [
                'code' => -32700,
                'message' => 'Parse error.'
            ],
            'id' => null,
        ];

        $this->setValidateMethodExpectation('never');
        $result = $this->executeControllerTest($data, $methodSpec);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }
}