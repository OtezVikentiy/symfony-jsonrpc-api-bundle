<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\RPC\V1\Test\TestRequest;
use OV\JsonRPCAPIBundle\RPC\V1\TestCallback\Request;
use OV\JsonRPCAPIBundle\RPC\V1\TestCallbackMethod;
use OV\JsonRPCAPIBundle\RPC\V1\TestMethod;
use Symfony\Component\HttpFoundation\JsonResponse;

final class AfterMethodCallbackTest extends AbstractTest
{
    public function testController()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'testCallback',
            'params' => [
                'title' => 'AZAZAZA',
            ],
            'id' => '1',
        ];

        $methodSpec = new MethodSpec(
            methodClass: TestCallbackMethod::class,
            requestType: 'POST',
            summary: '',
            description: '',
            ignoreInSwagger: false,
            methodName: 'testCallback',
            allParameters: [['name' => 'id', 'type' => 'int'], ['name' => 'title', 'type' => 'string']],
            requiredParameters: [['name' => 'id', 'type' => 'int']],
            request: Request::class,
            requestSetters: ['id' => 'setId', 'title' => 'setTitle'],
            validators: ['id' => ['allowsNull' => false, 'type' => 'int'], 'title' => ['allowsNull' => false, 'type' => 'string']],
            roles: [],
            plainResponse: false,
            callbacksExists: true
        );

        $responseData = [
            'jsonrpc' => '2.0',
            'result' => [
                'title' => 'AZAZAZA',
                'success' => true,
            ],
            'id' => '1',
        ];

        $result = $this->executeControllerTest($data, $methodSpec);


        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertStringEqualsFile('./tests/_tmp/AfterMethodCallbackTest.log', 'AfterMethodCallbackTest: AZAZAZA');
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }

    protected function after(): void
    {
        file_put_contents('./tests/_tmp/AfterMethodCallbackTest.log', '');
    }
}