<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Security;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\RPC\V1\Sum\SumRequest;
use OV\JsonRPCAPIBundle\RPC\V1\SumMethod;
use OV\JsonRPCAPIBundle\Tests\Controller\AbstractControllerTestCase;

final class BatchScalarElementTest extends AbstractControllerTestCase
{
    public function testScalarElementYieldsInvalidRequestWithoutKillingTheBatch(): void
    {
        $response = $this->executeControllerTest(
            [
                ['jsonrpc' => '2.0', 'method' => 'sum', 'params' => [1, 2, 4], 'id' => 1],
                1,
            ],
            $this->sumSpec(),
        );

        $payload = json_decode($response->getContent(), true);

        $this->assertIsArray($payload);
        $this->assertCount(2, $payload);
        $this->assertSame(7, $payload[0]['result']['result']);
        $this->assertSame(JRPCException::INVALID_REQUEST, $payload[1]['error']['code']);
        $this->assertNull($payload[1]['id']);
    }

    private function sumSpec(): MethodSpec
    {
        return new MethodSpec(
            methodClass: SumMethod::class,
            requestType: 'POST',
            methodName: 'sum',
            requestMetadata: new RequestMetadata(
                request: SumRequest::class,
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
