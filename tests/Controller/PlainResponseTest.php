<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
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
            summary: '',
            description: '',
            ignoreInSwagger: false,
            methodName: 'plainResponse',
            allParameters: [['name' => 'id', 'type' => 'int']],
            requiredParameters: [['name' => 'id', 'type' => 'int']],
            request: Request::class,
            requestSetters: ['id' => 'setId'],
            validators: ['id' => ['allowsNull' => false, 'type' => 'int']],
            roles: [],
            plainResponse: true,
            callbacksExists: false
        );

        $result = $this->executeControllerTest($data, $methodSpec);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals('image/png', $result->headers->get('Content-Type'));
        $this->assertEquals('some picture string', $result->getContent());
    }
}