<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
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
            summary: '',
            description: '',
            ignoreInSwagger: false,
            methodName: 'update',
            allParameters: [['name' => 'params', 'type' => 'array']],
            requiredParameters: [],
            request: UpdateRequest::class,
            requestSetters: ['params' => 'setParams'],
            validators: ['params' => ['allowsNull' => false, 'type' => 'array']]
        );

        $responseData = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32700,
                'message' => 'Parse error. Additional info: '
            ],
            //'id' => null //todo тот параметр сейчас не пробрасывается из-за настроек нормалайзера - он все null значения чистит
        ];

        $this->setValidateMethodExpectation('never');
        $result = $this->executeControllerTest($data, $methodSpec);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }
}