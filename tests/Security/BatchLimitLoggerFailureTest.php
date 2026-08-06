<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Security;

use OV\JsonRPCAPIBundle\Core\JRPCException;
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

final class BatchLimitLoggerFailureTest extends AbstractControllerTestCase
{
    private const OVERSIZED_BATCH_COUNT = 51;

    public function testExceptionFromApplyStrategyOutsideProcessBatchStillYieldsJsonRpcError(): void
    {
        $this->callLoggerOverride = new class implements JsonRpcCallLoggerInterface {
            public function logRequest(array $rpcCall): LoggedRpcCall
            {
                throw new RuntimeException('logger boom');
            }

            public function logRawRequest(string $rawBody): LoggedRpcCall
            {
                return new LoggedRpcCall('ctx', null, 0.0);
            }

            public function logResponse(LoggedRpcCall $call, ?OvResponseInterface $response): void
            {
            }
        };

        $item = ['jsonrpc' => '2.0', 'method' => 'sum', 'params' => [1, 2], 'id' => 1];
        $batch = array_fill(0, self::OVERSIZED_BATCH_COUNT, $item);

        $this->setValidateMethodExpectation('any');

        $response = $this->executeControllerTest($batch, $this->sumSpec());

        $payload = json_decode($response->getContent(), true);

        $this->assertIsArray($payload);
        $this->assertSame(JRPCException::INTERNAL_ERROR, $payload['error']['code']);
        $this->assertNull($payload['id']);
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
