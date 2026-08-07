<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Core\Services;

use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcCallLoggerInterface;
use OV\JsonRPCAPIBundle\Core\Logging\LoggedRpcCall;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\RPC\V1\Sum\SumRequest;
use OV\JsonRPCAPIBundle\RPC\V1\SumMethod;
use OV\JsonRPCAPIBundle\Tests\Controller\AbstractControllerTestCase;
use RuntimeException;

final class FinallyIsolationTest extends AbstractControllerTestCase
{
    public function testExceptionFromResponseLoggerDoesNotDestroyResponse(): void
    {
        $this->callLoggerOverride = new class implements JsonRpcCallLoggerInterface {
            public function logRequest(array $rpcCall): LoggedRpcCall
            {
                return new LoggedRpcCall('ctx', null, 0.0);
            }

            public function logRawRequest(string $rawBody): LoggedRpcCall
            {
                return new LoggedRpcCall('ctx', null, 0.0);
            }

            public function logResponse(LoggedRpcCall $call, ?OvResponseInterface $response): void
            {
                throw new RuntimeException('logger blew up');
            }
        };

        $response = $this->executeControllerTest(
            ['jsonrpc' => '2.0', 'method' => 'sum', 'params' => [1, 2, 4], 'id' => 1],
            $this->sumSpec(),
        );

        $payload = json_decode($response->getContent(), true);

        $this->assertSame(7, $payload['result']['result']);
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
