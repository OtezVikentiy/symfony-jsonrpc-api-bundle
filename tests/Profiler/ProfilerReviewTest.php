<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Profiler;

use OV\JsonRPCAPIBundle\Core\Logging\ContextIdGeneratorInterface;
use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcCallLoggerInterface;
use OV\JsonRPCAPIBundle\Core\Logging\LoggedRpcCall;
use OV\JsonRPCAPIBundle\Core\Logging\NullJsonRpcCallLogger;
use OV\JsonRPCAPIBundle\Core\Logging\SensitiveDataMasker;
use OV\JsonRPCAPIBundle\Core\Logging\SensitiveDataMaskerInterface;
use OV\JsonRPCAPIBundle\Core\Logging\UuidContextIdGenerator;
use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use OV\JsonRPCAPIBundle\Core\Response\PlainResponseInterface;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use OV\JsonRPCAPIBundle\Profiler\JsonRpcDataCollector;
use OV\JsonRPCAPIBundle\Profiler\TraceableJsonRpcCallLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

final class ProfilerReviewTest extends TestCase
{
    private function logger(?RequestStack $stack = null, int $limit = 4096, bool $skipPlain = true): TraceableJsonRpcCallLogger
    {
        return new TraceableJsonRpcCallLogger(new NullJsonRpcCallLogger(), new SensitiveDataMasker(['~password~'], '***', new NullLogger()), new UuidContextIdGenerator(), maxBodyLength: $limit, skipPlainResponses: $skipPlain, requestStack: $stack);
    }

    public function testSubrequestsAndSeparateDispatchesAreNotMergedIntoBatches(): void
    {
        $stack = new RequestStack();
        $main = Request::create('/api');
        $sub = Request::create('/fragment');
        $logger = $this->logger($stack);
        $stack->push($main);
        $logger->beginScope(true);
        $first = $logger->logRequest(['method' => 'first', 'id' => 1]);
        $stack->push($sub);
        $logger->beginScope(false);
        $child = $logger->logRequest(['method' => 'child', 'id' => 2]);
        $logger->logResponse($child, null);
        $logger->endScope();
        $stack->pop();
        $logger->logResponse($first, new JsonResponse(['result' => 1]));
        $logger->endScope();
        $last = $logger->logRawRequest('{broken');
        $logger->logResponse($last, new JsonResponse(['error' => ['code' => -32700]]));
        $collector = new JsonRpcDataCollector($logger, new MethodSpecCollection());
        $collector->collect($sub, new Response());
        self::assertSame(1, $collector->getCallCount());
        self::assertFalse($collector->getCallGroups()[0]['batch']);
        self::assertSame('child', $collector->getCallGroups()[0]['calls'][0]['method']);
        $collector->collect($main, new Response());
        self::assertSame(2, $collector->getCallCount());
        self::assertCount(2, $collector->getCallGroups());
        self::assertTrue($collector->getCallGroups()[0]['batch'], 'A one-element batch is still a batch');
        self::assertFalse($collector->getCallGroups()[1]['batch']);
        self::assertSame(1, $collector->getCallGroups()[0]['calls'][0]['response']['result']);
        $logger->reset();
        self::assertSame([], $logger->getCallsForRequest($main));
    }

    public function testRejectedSubrequestCannotInheritParentsBatchScope(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/api'));
        $logger = $this->logger($stack);
        $logger->beginScope(true);
        $parent = $logger->logRequest(['method' => 'parent']);
        $child = Request::create('/fragment');
        $stack->push($child);
        $logger->logRawRequest('{broken');
        self::assertNull($logger->getCallsForRequest($child)[0]['batchId']);
        $stack->pop();
        $logger->logResponse($parent, null);
        $logger->endScope();
    }

    public function testIndependentSingleCallsStaySeparate(): void
    {
        $logger = $this->logger();
        $logger->logRequest(['method' => 'a']);
        $logger->logRequest(['method' => 'b']);
        $collector = new JsonRpcDataCollector($logger, new MethodSpecCollection());
        $collector->collect(Request::create('/'), new Response());
        self::assertSame([false, false], array_column($collector->getCallGroups(), 'batch'));
    }

    public function testOversizedAndRecursivePayloadsAreOmittedBeforeMasking(): void
    {
        $masker = $this->createMock(SensitiveDataMaskerInterface::class);
        $masker->expects(self::never())->method('mask');
        $logger = new TraceableJsonRpcCallLogger(new NullJsonRpcCallLogger(), $masker, new UuidContextIdGenerator(), maxBodyLength: 128);
        $call = $logger->logRequest(['method' => 'large', 'params' => ['password' => str_repeat('secret', 10000)]]);
        $logger->logResponse($call, new JsonResponse(['result' => str_repeat('secret', 10000)]));
        $recursive = [];
        $recursive['recursive'] = &$recursive;
        $logger->logRequest($recursive);
        $stored = serialize($logger->getCalls());
        self::assertLessThan(2000, strlen($stored));
        self::assertStringNotContainsString('secret', $stored);
        self::assertStringContainsString('capture limit exceeded', $stored);
    }

    public function testRawJsonIsDecodedAndMaskedWithinBudget(): void
    {
        $logger = $this->logger();
        $logger->logRawRequest('{"method":"raw","params":{"password":"secret"}}');
        self::assertSame('***', $logger->getCalls()[0]['request']['params']['password']);
        self::assertSame('raw', $logger->getCalls()[0]['method']);
    }

    public function testPlainResponsePolicyAndHttpErrors(): void
    {
        $logger = $this->logger();
        $call = $logger->logRequest(['method' => 'plain']);
        $logger->logResponse($call, new ReviewPlainResponse('{"secret":"hidden"}', 500));
        self::assertStringContainsString('[plain response,', $logger->getCalls()[0]['response']);
        self::assertSame('error', $logger->getCalls()[0]['outcome']);
        $call = $logger->logRequest(['method' => 'json']);
        $logger->logResponse($call, new JsonResponse(['message' => 'failed'], 500));
        self::assertSame('error', $logger->getCalls()[1]['outcome']);
        $logger = $this->logger(skipPlain: false);
        $call = $logger->logRequest(['method' => 'plain']);
        $logger->logResponse($call, new ReviewPlainResponse('{"password":"secret"}'));
        self::assertSame(['password' => '***'], $logger->getCalls()[0]['response']);
    }

    public function testThrowingCaptureDoesNotBreakDelegation(): void
    {
        $inner = $this->createMock(JsonRpcCallLoggerInterface::class);
        $innerCall = new LoggedRpcCall('context', 'method', microtime(true));
        $inner->method('logRequest')->willReturn($innerCall);
        $inner->method('logRawRequest')->willReturn($innerCall);
        $inner->expects(self::exactly(2))->method('logResponse')->with($innerCall, null);
        $masker = $this->createMock(SensitiveDataMaskerInterface::class);
        $masker->method('mask')->willThrowException(new RuntimeException('secret failure'));
        $logger = new TraceableJsonRpcCallLogger($inner, $masker, new UuidContextIdGenerator());
        $logger->logResponse($logger->logRequest(['method' => 'test']), null);
        $logger->logResponse($logger->logRawRequest('{"method":"test"}'), null);
        self::assertSame([], $logger->getCalls());
    }

    public function testThrowingResponseMaskerAndContextGeneratorDegradeSafely(): void
    {
        $generator = $this->createMock(ContextIdGeneratorInterface::class);
        $generator->method('generate')->willThrowException(new RuntimeException('secret'));
        $masker = $this->createMock(SensitiveDataMaskerInterface::class);
        $masker->method('mask')->willReturnCallback(static function (array $data): array {
            if (isset($data['result'])) {
                throw new RuntimeException('secret');
            }

            return $data;
        });
        $logger = new TraceableJsonRpcCallLogger(new NullJsonRpcCallLogger(), $masker, $generator);
        $call = $logger->logRequest(['method' => 'test']);
        $logger->logResponse($call, new JsonResponse(['result' => 'secret']));
        self::assertSame('unavailable', $logger->getCalls()[0]['outcome']);
        self::assertStringNotContainsString('secret', serialize($logger->getCalls()));
    }

    public function testEmptyHttpRequestDoesNotMaterializeTheRegistry(): void
    {
        $methods = new MethodSpecCollection();
        $collector = new JsonRpcDataCollector($this->logger(), $methods);
        $collector->collect(Request::create('/'), new Response());
        self::assertSame([], $collector->getMethods());
    }
}

final class ReviewPlainResponse extends Response implements PlainResponseInterface
{
}
