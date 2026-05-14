<?php

namespace OV\JsonRPCAPIBundle\Tests\Logging;

use OV\JsonRPCAPIBundle\Core\Logging\NullJsonRpcCallLogger;
use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

final class NullJsonRpcCallLoggerTest extends TestCase
{
    public function testLogRequestReturnsValidEmptyScope(): void
    {
        $logger = new NullJsonRpcCallLogger();
        $call = $logger->logRequest(['method' => 'x']);

        self::assertSame('', $call->contextId);
        self::assertNull($call->method);
    }

    public function testLogRawRequestReturnsValidEmptyScope(): void
    {
        $logger = new NullJsonRpcCallLogger();
        $call = $logger->logRawRequest('{}');

        self::assertSame('', $call->contextId);
        self::assertNull($call->method);
    }

    #[DoesNotPerformAssertions]
    public function testLogResponseIsNoOp(): void
    {
        $logger = new NullJsonRpcCallLogger();
        $call = $logger->logRequest(['method' => 'x']);

        $logger->logResponse($call, new JsonResponse('{}', json: true));
        $logger->logResponse($call, null);
    }

    public function testRepeatedCallsReturnSameInstance(): void
    {
        $logger = new NullJsonRpcCallLogger();

        $a = $logger->logRequest(['method' => 'x']);
        $b = $logger->logRequest(['method' => 'y']);
        $c = $logger->logRawRequest('garbage');

        self::assertSame($a, $b);
        self::assertSame($a, $c);
    }
}
