<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Profiler;

use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcCallLoggerInterface;
use OV\JsonRPCAPIBundle\Core\Logging\LoggedRpcCall;
use OV\JsonRPCAPIBundle\Core\Logging\NullJsonRpcCallLogger;
use OV\JsonRPCAPIBundle\Core\Logging\SensitiveDataMasker;
use OV\JsonRPCAPIBundle\Core\Logging\UuidContextIdGenerator;
use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;
use OV\JsonRPCAPIBundle\Profiler\TraceableJsonRpcCallLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class TraceableJsonRpcCallLoggerTest extends TestCase
{
    public function testItCollectsAndMasksWhenTheConfiguredLoggerIsNull(): void
    {
        $logger = $this->logger(new NullJsonRpcCallLogger());
        $call = $logger->logRequest([
            'jsonrpc' => '2.0',
            'method' => 'account.update',
            'params' => ['password' => 'do-not-show', 'name' => 'Ada'],
            'id' => 7,
        ]);
        $logger->logResponse($call, new JsonResponse([
            'jsonrpc' => '2.0',
            'error' => ['code' => -32602, 'message' => 'Invalid params'],
            'id' => 7,
        ]));

        $calls = $logger->getCalls();
        self::assertCount(1, $calls);
        self::assertSame('account.update', $calls[0]['method']);
        self::assertSame(7, $calls[0]['id']);
        self::assertSame('***', $calls[0]['request']['params']['password']);
        self::assertSame('Ada', $calls[0]['request']['params']['name']);
        self::assertSame('error', $calls[0]['outcome']);
        self::assertSame(-32602, $calls[0]['errorCode']);
        self::assertGreaterThanOrEqual(0.0, $calls[0]['durationMs']);
        self::assertNotSame('', $calls[0]['contextId']);
    }

    public function testItKeepsTheDecoratedLoggerContextIdAndDelegatesItsScope(): void
    {
        $inner = new RecordingCallLogger();
        $logger = $this->logger($inner);
        $call = $logger->logRequest(['method' => 'task.list', 'id' => 'abc']);
        $logger->logResponse($call, null);

        self::assertSame('inner-context', $call->contextId);
        self::assertSame($inner->issuedCall, $inner->receivedCall);
        self::assertSame('notification', $logger->getCalls()[0]['outcome']);
    }

    public function testCompletedScopesRemainDistinctWhenPhpReusesObjectIds(): void
    {
        $logger = $this->logger(new NullJsonRpcCallLogger());

        for ($id = 1; $id <= 20; ++$id) {
            $call = $logger->logRequest(['method' => 'task.get', 'id' => $id]);
            $logger->logResponse($call, new JsonResponse(['result' => $id, 'id' => $id]));
            unset($call);
        }

        self::assertCount(20, $logger->getCalls());
        self::assertSame(range(1, 20), array_column($logger->getCalls(), 'id'));
    }

    public function testRawRequestContentIsNeverStored(): void
    {
        $logger = $this->logger(new NullJsonRpcCallLogger());
        $call = $logger->logRawRequest('{"password":"still-secret"');
        $logger->logResponse($call, new JsonResponse(['error' => ['code' => -32700]]));

        $request = $logger->getCalls()[0]['request'];
        self::assertSame(['rawBody' => '[unparseable body, 26 bytes]'], $request);
        self::assertStringNotContainsString('still-secret', json_encode($request) ?: '');
    }

    private function logger(JsonRpcCallLoggerInterface $inner): TraceableJsonRpcCallLogger
    {
        return new TraceableJsonRpcCallLogger(
            $inner,
            new SensitiveDataMasker(['~password~i'], '***', new NullLogger()),
            new UuidContextIdGenerator(),
        );
    }
}

final class RecordingCallLogger implements JsonRpcCallLoggerInterface
{
    public ?LoggedRpcCall $issuedCall = null;
    public ?LoggedRpcCall $receivedCall = null;

    public function logRequest(array $rpcCall): LoggedRpcCall
    {
        return $this->issuedCall = new LoggedRpcCall('inner-context', 'task.list', microtime(true));
    }

    public function logRawRequest(string $rawBody): LoggedRpcCall
    {
        return $this->issuedCall = new LoggedRpcCall('inner-context', null, microtime(true));
    }

    public function logResponse(LoggedRpcCall $call, ?OvResponseInterface $response): void
    {
        $this->receivedCall = $call;
    }
}
