<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\RPC\V1\Test\TestRequest;
use OV\JsonRPCAPIBundle\RPC\V1\TestPreProcessor\Request;
use OV\JsonRPCAPIBundle\RPC\V1\TestPreProcessorMethod;
use OV\JsonRPCAPIBundle\RPC\V1\TestMethod;
use Symfony\Component\HttpFoundation\JsonResponse;

final class BeforeMethodPreProcessorTest extends AbstractTest
{
    public function testController()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'testPreProcessor',
            'params' => [
                'title' => 'AZAZAZA',
            ],
            'id' => '1',
        ];

        $methodSpec = new MethodSpec(
            methodClass: TestPreProcessorMethod::class,
            requestType: 'POST',
            summary: '',
            description: '',
            ignoreInSwagger: false,
            methodName: 'testPreProcessor',
            allParameters: [['name' => 'id', 'type' => 'int'], ['name' => 'title', 'type' => 'string']],
            requiredParameters: [['name' => 'id', 'type' => 'int']],
            request: Request::class,
            requestSetters: ['id' => 'setId', 'title' => 'setTitle'],
            validators: ['id' => ['allowsNull' => false, 'type' => 'int'], 'title' => ['allowsNull' => false, 'type' => 'string']],
            roles: [],
            plainResponse: false,
            preProcessorExists: true
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
        $this->assertStringEqualsFile('./tests/_tmp/AfterMethodPreProcessorsTest.log', 'AfterMethodPreProcessorsTest: AZAZAZA');
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }

    protected function after(): void
    {
        file_put_contents('./tests/_tmp/AfterMethodPreProcessorsTest.log', '');
    }
}