<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\RPC\V1\TestPreProcessor\Request;
use OV\JsonRPCAPIBundle\RPC\V1\TestPreProcessorMethod;
use Symfony\Component\HttpFoundation\JsonResponse;

final class BeforeMethodPreProcessorTest extends AbstractTest
{
    public function testController()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'testPreProcessor',
            'params' => [
                'id' => 10,
                'title' => 'AZAZAZA',
            ],
            'id' => '1',
        ];

        $methodSpec = new MethodSpec(
            methodClass: TestPreProcessorMethod::class,
            requestType: 'POST',
            methodName: 'testPreProcessor',
            requestMetadata: new RequestMetadata(
                request: Request::class,
                allParameters: [['name' => 'id', 'type' => 'int'], ['name' => 'title', 'type' => 'string']],
                requiredParameters: [['name' => 'id', 'type' => 'int']],
                requestGetters: ['id' => 'getId', 'title' => 'getTitle'],
                requestSetters: ['id' => 'setId', 'title' => 'setTitle'],
                requestAdders: [],
                validators: ['id' => ['allowsNull' => false, 'type' => 'int'], 'title' => ['allowsNull' => false, 'type' => 'string']],
            ),
            swaggerMetadata: new SwaggerMetadata(
                summary: '',
                description: '',
                ignoreInSwagger: false,
            ),
            preProcessorExists: true,
        );

        $responseData = [
            'jsonrpc' => '2.0',
            'result' => [
                'success' => true,
                'title' => 'AZAZAZA',
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