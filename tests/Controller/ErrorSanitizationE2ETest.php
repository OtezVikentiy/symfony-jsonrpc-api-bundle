<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\RPC\V1\Throwing\ThrowingRequest;
use OV\JsonRPCAPIBundle\RPC\V1\ThrowingMethod;

final class ErrorSanitizationE2ETest extends AbstractControllerTestCase
{
    public function testInternalExceptionIsSanitizedEndToEnd(): void
    {
        $response = $this->executeControllerTest(
            ['jsonrpc' => '2.0', 'method' => 'throwing', 'params' => [], 'id' => 1],
            $this->throwingSpec(),
        );

        $payload = json_decode($response->getContent(), true);

        $this->assertSame(JRPCException::INTERNAL_ERROR, $payload['error']['code']);
        $this->assertSame('Internal error.', $payload['error']['message']);
        $this->assertStringNotContainsString('10.0.0.5', $response->getContent());
        $this->assertStringNotContainsString('app_rw', $response->getContent());
        $this->assertStringNotContainsString('RuntimeException', $response->getContent());
    }

    private function throwingSpec(): MethodSpec
    {
        return new MethodSpec(
            methodClass: ThrowingMethod::class,
            requestType: 'POST',
            methodName: 'throwing',
            requestMetadata: new RequestMetadata(
                request: ThrowingRequest::class,
                allParameters: [['name' => 'params', 'type' => 'array']],
                requiredParameters: [],
                requestGetters: ['params' => 'getParams'],
                requestSetters: ['params' => 'setParams'],
                requestAdders: [],
                validators: [],
            ),
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: true),
        );
    }
}
