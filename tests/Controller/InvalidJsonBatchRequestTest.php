<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\RPC\V1\Update\UpdateRequest;
use OV\JsonRPCAPIBundle\RPC\V1\UpdateMethod;
use Symfony\Component\HttpFoundation\JsonResponse;

final class InvalidJsonBatchRequestTest extends AbstractControllerTestCase
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

        $this->setValidateMethodExpectation('never');
        $result = $this->executeControllerTest($data, $methodSpec);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());

        // JSON-RPC 2.0 spec 5.1: invalid JSON -> -32700 Parse error. The tail of
        // the message is JsonException's own text - docs/upgrade-4.0.md documents
        // it as "or other text from JsonException" - and PHP 8.6 appends the error
        // location ("Syntax error near location 1:95"). So the structure and the
        // version-independent prefix are the requirement; the exact full string
        // never was and is pinned no more.
        $decoded = json_decode($result->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['jsonrpc', 'error', 'id'], array_keys($decoded));
        $this->assertSame('2.0', $decoded['jsonrpc']);
        $this->assertNull($decoded['id']);
        $this->assertSame(['code', 'message'], array_keys($decoded['error']));
        $this->assertSame(-32700, $decoded['error']['code']);
        $this->assertStringStartsWith('Parse error. Additional info: Syntax error', $decoded['error']['message']);
    }
}