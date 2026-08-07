<?php

namespace OV\JsonRPCAPIBundle\Tests\Logging;

use OV\JsonRPCAPIBundle\Core\Logging\ContextIdGeneratorInterface;
use OV\JsonRPCAPIBundle\Core\Logging\DefaultJsonRpcLogFormatter;
use OV\JsonRPCAPIBundle\Core\Logging\FormattedLogEntry;
use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcCallLogger;
use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcLogEntry;
use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcLogFormatterInterface;
use OV\JsonRPCAPIBundle\Core\Logging\SensitiveDataMasker;
use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use OV\JsonRPCAPIBundle\Tests\Fixtures\Logging\InMemoryPlainResponse;
use OV\JsonRPCAPIBundle\Tests\Fixtures\TestLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Psr\Log\LogLevel;

final class JsonRpcCallLoggerTest extends TestCase
{
    /**
     * @param list<string> $maskPatterns
     */
    private function makeLogger(
        TestLogger $sink,
        array $maskPatterns = [],
        int $maxBodyLength = 0,
        bool $skipPlain = true,
        int $maxJsonDepth = 64,
        int $maxPayloadBytes = 1048576,
    ): JsonRpcCallLogger {
        $generator = new class implements ContextIdGeneratorInterface {
            public int $counter = 0;
            public function generate(): string {
                return 'ctx-' . (++$this->counter);
            }
        };

        return new JsonRpcCallLogger(
            logger: $sink,
            formatter: new DefaultJsonRpcLogFormatter(LogLevel::INFO, LogLevel::INFO, LogLevel::WARNING),
            masker: new SensitiveDataMasker($maskPatterns, '***', new NullLogger()),
            contextIdGenerator: $generator,
            maxBodyLength: $maxBodyLength,
            skipPlainResponses: $skipPlain,
            maxJsonDepth: $maxJsonDepth,
            maxPayloadBytes: $maxPayloadBytes,
        );
    }

    public function testLogsRequestWithMethodAndContextId(): void
    {
        $sink = new TestLogger();
        $logger = $this->makeLogger($sink);

        $call = $logger->logRequest([
            'jsonrpc' => '2.0',
            'method' => 'get_x',
            'params' => ['a' => 1],
            'id' => 1,
        ]);

        self::assertSame('get_x', $call->method);
        self::assertSame('ctx-1', $call->contextId);
        self::assertCount(1, $sink->records);
        self::assertSame(LogLevel::INFO, $sink->records[0]['level']);
        self::assertStringContainsString('Request: [get_x] ', $sink->records[0]['message']);
        self::assertStringContainsString('context_id: ctx-1', $sink->records[0]['message']);
    }

    public function testLogsResponseUsingCallScope(): void
    {
        $sink = new TestLogger();
        $logger = $this->makeLogger($sink);

        $call = $logger->logRequest(['method' => 'get_x', 'id' => 1]);
        $response = new JsonResponse('{"jsonrpc":"2.0","result":{"ok":true},"id":1}', json: true);

        $logger->logResponse($call, $response);

        self::assertCount(2, $sink->records);
        self::assertStringContainsString('Response: [get_x]', $sink->records[1]['message']);
        self::assertStringContainsString('context_id: ctx-1', $sink->records[1]['message']);
    }

    public function testNotificationResponseIsMarked(): void
    {
        $sink = new TestLogger();
        $logger = $this->makeLogger($sink);

        $call = $logger->logRequest(['method' => 'notify', 'params' => []]);
        $logger->logResponse($call, null);

        self::assertStringContainsString('[no response - notification]', $sink->records[1]['message']);
    }

    public function testPlainResponseIsMaskedAsBinaryMarker(): void
    {
        $sink = new TestLogger();
        $logger = $this->makeLogger($sink);

        $plain = new InMemoryPlainResponse(str_repeat('x', 1234));

        $call = $logger->logRequest(['method' => 'download', 'id' => 1]);
        $logger->logResponse($call, $plain);

        self::assertStringContainsString('[plain response, 1234 bytes]', $sink->records[1]['message']);
    }

    public function testErrorResponseUsesWarningLevel(): void
    {
        $sink = new TestLogger();
        $logger = $this->makeLogger($sink);

        $call = $logger->logRequest(['method' => 'broken', 'id' => 1]);
        $logger->logResponse(
            $call,
            new JsonResponse('{"jsonrpc":"2.0","error":{"code":-32603,"message":"Internal error."},"id":1}', json: true),
        );

        self::assertSame(LogLevel::WARNING, $sink->records[1]['level']);
    }

    public function testMaskingAppliedToRequest(): void
    {
        $sink = new TestLogger();
        $logger = $this->makeLogger($sink, ['~^password$~i']);

        $logger->logRequest([
            'method' => 'login',
            'params' => ['user' => 'u', 'password' => 'p4ss'],
            'id' => 1,
        ]);

        self::assertStringContainsString('"password":"***"', $sink->records[0]['message']);
        self::assertStringNotContainsString('p4ss', $sink->records[0]['message']);
    }

    public function testLogRawRequestUsesUnparseableMarkerForGarbage(): void
    {
        $sink = new TestLogger();
        $logger = $this->makeLogger($sink);

        $call = $logger->logRawRequest('<garbage that is not json>');

        self::assertNull($call->method);
        self::assertStringContainsString('Request: [unknown]', $sink->records[0]['message']);
        self::assertStringContainsString('[unparseable body,', $sink->records[0]['message']);
    }

    public function testLogRawRequestMasksWhenBodyParseable(): void
    {
        $sink = new TestLogger();
        $logger = $this->makeLogger($sink, ['~^token$~i']);

        $logger->logRawRequest('{"method":"x","params":{"token":"secret"}}');

        self::assertStringContainsString('"token":"***"', $sink->records[0]['message']);
        self::assertStringNotContainsString('secret', $sink->records[0]['message']);
    }

    public function testMaxBodyLengthTruncates(): void
    {
        $sink = new TestLogger();
        $logger = $this->makeLogger($sink, [], maxBodyLength: 50);

        $logger->logRequest([
            'method' => 'big',
            'params' => ['data' => str_repeat('x', 200)],
            'id' => 1,
        ]);

        self::assertStringContainsString('...[truncated,', $sink->records[0]['message']);
    }

    public function testMethodIsTruncatedIndependentlyOfMaxBodyLength(): void
    {
        $sink = new TestLogger();
        // max_body_length is tight (200), matching the audit's measurement setup: the attacker-controlled
        // method must be bounded on its own, not merely as a side effect of the overall body truncation.
        $logger = $this->makeLogger($sink, [], maxBodyLength: 200);

        $hugeMethod = str_repeat('x', 50173);
        $call = $logger->logRequest(['method' => $hugeMethod, 'id' => 1]);

        self::assertSame(128, strlen((string) $call->method));
        self::assertLessThan(50173, strlen($sink->records[0]['message']));
        self::assertStringNotContainsString($hugeMethod, $sink->records[0]['message']);
    }

    public function testLogRawRequestBoundsDecodeToMaxPayloadBytes(): void
    {
        $sink = new TestLogger();
        // A tiny maxPayloadBytes forces the raw body to be truncated before json_decode ever runs.
        // Without that bound the full body below is valid JSON and would decode, method and all.
        $logger = $this->makeLogger($sink, [], maxPayloadBytes: 10);

        $rawBody = '{"method":"evil","params":"' . str_repeat('x', 1000) . '"}';
        $call = $logger->logRawRequest($rawBody);

        self::assertNull($call->method);
        self::assertStringContainsString('[unparseable body,', $sink->records[0]['message']);
        self::assertStringNotContainsString('evil', $sink->records[0]['message']);
        self::assertStringNotContainsString(str_repeat('x', 1000), $sink->records[0]['message']);
    }

    public function testLogRawRequestBoundsDecodeToMaxJsonDepth(): void
    {
        $sink = new TestLogger();
        $logger = $this->makeLogger($sink, [], maxJsonDepth: 5);

        // 200 levels of nesting: well within PHP's default json_decode depth (512), so without the
        // maxJsonDepth bound this decodes successfully; with it, decoding must fail past depth 5.
        $deeplyNested = str_repeat('[', 200) . '1' . str_repeat(']', 200);
        $logger->logRawRequest($deeplyNested);

        self::assertStringContainsString('[unparseable body,', $sink->records[0]['message']);
    }

    public function testInternalFailureIsSwallowedAndScopeReturned(): void
    {
        $sink = new TestLogger();

        $brokenFormatter = new class implements JsonRpcLogFormatterInterface {
            public function format(JsonRpcLogEntry $entry): FormattedLogEntry {
                throw new \RuntimeException('formatter boom');
            }
        };

        $logger = new JsonRpcCallLogger(
            logger: $sink,
            formatter: $brokenFormatter,
            masker: new SensitiveDataMasker([], '***', new NullLogger()),
            contextIdGenerator: new class implements ContextIdGeneratorInterface {
                public function generate(): string { return 'ctx-1'; }
            },
            maxBodyLength: 0,
            skipPlainResponses: true,
        );

        $call = $logger->logRequest(['method' => 'x', 'id' => 1]);

        self::assertNotNull($call);
        self::assertTrue($sink->hasErrorRecords());
    }

    public function testLogRequestSwallowsThrowableFromContextIdGenerator(): void
    {
        $sink = new TestLogger();
        $logger = new JsonRpcCallLogger(
            logger: $sink,
            formatter: new DefaultJsonRpcLogFormatter(LogLevel::INFO, LogLevel::INFO, LogLevel::WARNING),
            masker: new SensitiveDataMasker([], '***', new NullLogger()),
            contextIdGenerator: new class implements ContextIdGeneratorInterface {
                public function generate(): string {
                    throw new \RuntimeException('ctx generator boom');
                }
            },
            maxBodyLength: 0,
            skipPlainResponses: true,
        );

        $call = $logger->logRequest(['method' => 'x', 'id' => 1]);

        // Fallback scope returned with sentinel zero-UUID.
        self::assertSame('00000000-0000-0000-0000-000000000000', $call->contextId);
        self::assertNull($call->method);
        self::assertTrue($sink->hasErrorRecords());
    }

    public function testLogRawRequestSwallowsThrowableFromContextIdGenerator(): void
    {
        $sink = new TestLogger();
        $logger = new JsonRpcCallLogger(
            logger: $sink,
            formatter: new DefaultJsonRpcLogFormatter(LogLevel::INFO, LogLevel::INFO, LogLevel::WARNING),
            masker: new SensitiveDataMasker([], '***', new NullLogger()),
            contextIdGenerator: new class implements ContextIdGeneratorInterface {
                public function generate(): string {
                    throw new \RuntimeException('ctx generator boom');
                }
            },
            maxBodyLength: 0,
            skipPlainResponses: true,
        );

        $call = $logger->logRawRequest('{"method":"x"}');

        self::assertSame('00000000-0000-0000-0000-000000000000', $call->contextId);
        self::assertTrue($sink->hasErrorRecords());
    }

    public function testEncodeBodyReturnsMarkerWhenJsonEncodeFails(): void
    {
        $sink = new TestLogger();
        $logger = $this->makeLogger($sink);

        // NAN cannot be json-encoded (without JSON_PARTIAL_OUTPUT_ON_ERROR), forces json_encode to return false.
        $logger->logRequest([
            'method' => 'bad',
            'params' => ['n' => NAN],
            'id' => 1,
        ]);

        self::assertStringContainsString('[json-encode-failed]', $sink->records[0]['message']);
    }

    public function testNonJsonResponseBodyMarkerWhenSkipPlainIsFalse(): void
    {
        $sink = new TestLogger();
        // skipPlain=false → fall through to json_decode; passing a non-JSON body triggers the [non-json] marker.
        $logger = $this->makeLogger($sink, [], 0, false);

        $call = $logger->logRequest(['method' => 'x', 'id' => 1]);
        // Plain response with non-JSON body. With skipPlain=false the logger tries json_decode and falls to the
        // [non-json response, N bytes] marker.
        $logger->logResponse($call, new InMemoryPlainResponse('not json'));

        self::assertStringContainsString('[non-json response, 8 bytes]', $sink->records[1]['message']);
    }

    public function testLogResponseFailureIsSwallowed(): void
    {
        $sink = new TestLogger();

        $brokenFormatter = new class implements JsonRpcLogFormatterInterface {
            public bool $failNext = false;
            public function format(JsonRpcLogEntry $entry): FormattedLogEntry {
                if ($this->failNext) {
                    throw new \RuntimeException('formatter boom on response');
                }
                return new FormattedLogEntry(message: 'noop', context: [], level: 'info');
            }
        };

        $logger = new JsonRpcCallLogger(
            logger: $sink,
            formatter: $brokenFormatter,
            masker: new SensitiveDataMasker([], '***', new NullLogger()),
            contextIdGenerator: new class implements ContextIdGeneratorInterface {
                public function generate(): string { return 'ctx-1'; }
            },
            maxBodyLength: 0,
            skipPlainResponses: true,
        );

        $call = $logger->logRequest(['method' => 'x', 'id' => 1]);
        $brokenFormatter->failNext = true;

        $logger->logResponse($call, new JsonResponse('{"result":1}', json: true));

        self::assertTrue($sink->hasErrorRecords());
    }
}
