<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\RPC\V1\Test\TestRequest;
use OV\JsonRPCAPIBundle\RPC\V1\TestMethod;
use Symfony\Component\HttpFoundation\JsonResponse;

final class BaseSimpleControllerTest extends AbstractTest
{
    public function testController()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'params' => [
                'title' => 'AZAZAZA',
            ],
            'id' => '1',
        ];

        $methodSpec = new MethodSpec(
            methodClass: TestMethod::class,
            requestType: 'POST',
            summary: '',
            description: '',
            ignoreInSwagger: false,
            methodName: 'test',
            allParameters: [['name' => 'id', 'type' => 'int'], ['name' => 'title', 'type' => 'string']],
            requiredParameters: [['name' => 'id', 'type' => 'int']],
            request: TestRequest::class,
            requestSetters: ['id' => 'setId', 'title' => 'setTitle'],
            validators: ['id' => ['allowsNull' => false, 'type' => 'int'], 'title' => ['allowsNull' => false, 'type' => 'string']]
        );

        $responseData = [
            'jsonrpc' => '2.0',
            'result' => [
                'title' => 'AZAZAZA',
                'success' => true,
                'request' => null,
                'tests' => [],
                'errors' => [],
            ],
            'id' => '1',
        ];

        $result = $this->executeControllerTest($data, $methodSpec);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }
}