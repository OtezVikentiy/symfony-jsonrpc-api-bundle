<?php

namespace OV\JsonRPCAPIBundle\Tests\Logging;

use OV\JsonRPCAPIBundle\Core\Logging\DefaultJsonRpcLogFormatter;
use OV\JsonRPCAPIBundle\Core\Logging\Direction;
use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcLogEntry;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class DefaultJsonRpcLogFormatterTest extends TestCase
{
    public function testFormatsRequestWithMethod(): void
    {
        $formatter = new DefaultJsonRpcLogFormatter(LogLevel::INFO, LogLevel::INFO, LogLevel::WARNING);
        $entry = new JsonRpcLogEntry(
            direction: Direction::Request,
            method: 'get_billing_operations',
            body: '{"jsonrpc":"2.0","method":"get_billing_operations","params":{},"id":1}',
            contextId: 'abc-123',
        );

        $formatted = $formatter->format($entry);

        self::assertSame(
            'Request: [get_billing_operations] {"jsonrpc":"2.0","method":"get_billing_operations","params":{},"id":1} context_id: abc-123',
            $formatted->message,
        );
        self::assertSame(LogLevel::INFO, $formatted->level);
        self::assertSame(
            ['method' => 'get_billing_operations', 'context_id' => 'abc-123', 'direction' => 'request'],
            $formatted->context,
        );
    }

    public function testFormatsResponseWithMethod(): void
    {
        $formatter = new DefaultJsonRpcLogFormatter(LogLevel::INFO, LogLevel::INFO, LogLevel::WARNING);
        $entry = new JsonRpcLogEntry(
            direction: Direction::Response,
            method: 'get_billing_operations',
            body: '{"jsonrpc":"2.0","result":{"count":42},"id":1}',
            contextId: 'abc-123',
        );

        $formatted = $formatter->format($entry);

        self::assertSame(
            'Response: [get_billing_operations] {"jsonrpc":"2.0","result":{"count":42},"id":1} context_id: abc-123',
            $formatted->message,
        );
        self::assertSame(LogLevel::INFO, $formatted->level);
    }

    public function testFormatsResponseWithErrorAtWarningLevel(): void
    {
        $formatter = new DefaultJsonRpcLogFormatter(LogLevel::INFO, LogLevel::INFO, LogLevel::WARNING);
        $entry = new JsonRpcLogEntry(
            direction: Direction::Response,
            method: 'get_x',
            body: '{"jsonrpc":"2.0","error":{"code":-32603,"message":"Internal error."},"id":1}',
            contextId: 'abc-123',
        );

        $formatted = $formatter->format($entry);

        self::assertSame(LogLevel::WARNING, $formatted->level);
    }

    public function testUnknownMethodIsRenderedAsUnknown(): void
    {
        $formatter = new DefaultJsonRpcLogFormatter(LogLevel::INFO, LogLevel::INFO, LogLevel::WARNING);
        $entry = new JsonRpcLogEntry(
            direction: Direction::Request,
            method: null,
            body: '<garbage>',
            contextId: 'abc-123',
        );

        $formatted = $formatter->format($entry);

        self::assertStringStartsWith('Request: [unknown] ', $formatted->message);
        self::assertSame('unknown', $formatted->context['method']);
    }

    public function testHttp4xxResponseEscalatesToErrorLevel(): void
    {
        $formatter = new DefaultJsonRpcLogFormatter(LogLevel::INFO, LogLevel::INFO, LogLevel::WARNING);
        $entry = new JsonRpcLogEntry(
            direction: Direction::Response,
            method: 'protected',
            body: '"Access not allowed"',
            contextId: 'abc-123',
            meta: ['http_status' => 403],
        );

        $formatted = $formatter->format($entry);

        self::assertSame(LogLevel::WARNING, $formatted->level);
    }

    public function testHttp2xxResponseKeepsSuccessLevel(): void
    {
        $formatter = new DefaultJsonRpcLogFormatter(LogLevel::INFO, LogLevel::INFO, LogLevel::WARNING);
        $entry = new JsonRpcLogEntry(
            direction: Direction::Response,
            method: 'ok',
            body: '{"result":1}',
            contextId: 'abc-123',
            meta: ['http_status' => 200],
        );

        $formatted = $formatter->format($entry);

        self::assertSame(LogLevel::INFO, $formatted->level);
    }

    public function testNonJsonResponseBodyKeepsSuccessLevel(): void
    {
        $formatter = new DefaultJsonRpcLogFormatter(LogLevel::INFO, LogLevel::INFO, LogLevel::WARNING);
        $entry = new JsonRpcLogEntry(
            direction: Direction::Response,
            method: 'download',
            body: '[plain response, 1234 bytes]',
            contextId: 'abc-123',
        );

        $formatted = $formatter->format($entry);

        self::assertSame(LogLevel::INFO, $formatted->level);
    }
}
