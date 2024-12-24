<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\RPC\V1\Update\UpdateRequest;
use OV\JsonRPCAPIBundle\RPC\V1\UpdateMethod;
use Symfony\Component\HttpFoundation\JsonResponse;

final class EmptyArrayRequestTest extends AbstractTest
{
    public function testRpcCallWithAnEmptyArray()
    {
        $data = '[]';

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
                'code' => -32600,
                'message' => 'Invalid Request. Additional info: '
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