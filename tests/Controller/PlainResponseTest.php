<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\RPC\V1\PlainResponse\Request;
use OV\JsonRPCAPIBundle\RPC\V1\PlainResponseMethod;
use Symfony\Component\HttpFoundation\Response;

final class PlainResponseTest extends AbstractTest
{
    public function testController()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'plainResponse',
            'id' => '5',
        ];

        $methodSpec = new MethodSpec(
            methodClass: PlainResponseMethod::class,
            requestType: 'POST',
            methodName: 'plainResponse',
            requestMetadata: new RequestMetadata(
                request: Request::class,
                allParameters: [['name' => 'id', 'type' => 'int']],
                requiredParameters: [['name' => 'id', 'type' => 'int']],
                requestGetters: ['id' => 'getId'],
                requestSetters: ['id' => 'setId'],
                requestAdders: [],
                validators: ['id' => ['allowsNull' => false, 'type' => 'int']],
            ),
            swaggerMetadata: new SwaggerMetadata(
                summary: '',
                description: '',
                ignoreInSwagger: false,
            ),
            plainResponse: true,
        );

        $result = $this->executeControllerTest($data, $methodSpec);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals('image/png', $result->headers->get('Content-Type'));
        $this->assertEquals('some picture string', $result->getContent());
    }
}