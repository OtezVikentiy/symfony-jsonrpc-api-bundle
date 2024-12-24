<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\RPC\V1\Update\UpdateRequest;
use OV\JsonRPCAPIBundle\RPC\V1\UpdateMethod;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ResponseWithoutPropertiesTest extends AbstractTest
{
    public function testNotification()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'update',
            'params' => [1, 2, 3, 4, 5],
        ];

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

        $responseData = '{}';

        $result = $this->executeControllerTest($data, $methodSpec);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals($responseData, $result->getContent());
    }
}