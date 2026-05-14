<?php

namespace OV\JsonRPCAPIBundle\Tests\Logging;

use OV\JsonRPCAPIBundle\Core\Logging\DefaultJsonRpcLogFormatter;
use OV\JsonRPCAPIBundle\Core\Logging\Direction;
use OV\JsonRPCAPIBundle\Core\Logging\FormattedLogEntry;
use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcCallLogger;
use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcLogEntry;
use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcLogFormatterInterface;
use OV\JsonRPCAPIBundle\Core\Logging\SensitiveDataMasker;
use OV\JsonRPCAPIBundle\Core\Logging\UuidContextIdGenerator;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\RPC\V1\Subtract\SubtractRequest;
use OV\JsonRPCAPIBundle\RPC\V1\SubtractMethod;
use OV\JsonRPCAPIBundle\Tests\Controller\AbstractControllerTestCase;
use OV\JsonRPCAPIBundle\Tests\Fixtures\TestLogger;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

final class LoggingIntegrationTest extends AbstractControllerTestCase
{
    private TestLogger $sink;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sink = new TestLogger();
        $this->callLoggerOverride = $this->makeCallLogger($this->sink, []);
    }

    /**
     * @param list<string> $maskPatterns
     */
    private function makeCallLogger(TestLogger $sink, array $maskPatterns, int $maxBodyLength = 0): JsonRpcCallLogger
    {
        return new JsonRpcCallLogger(
            logger: $sink,
            formatter: new DefaultJsonRpcLogFormatter(LogLevel::INFO, LogLevel::INFO, LogLevel::WARNING),
            masker: new SensitiveDataMasker($maskPatterns, '***', new NullLogger()),
            contextIdGenerator: new UuidContextIdGenerator(),
            maxBodyLength: $maxBodyLength,
            skipPlainResponses: true,
        );
    }

    private function subtractMethodSpec(): MethodSpec
    {
        return new MethodSpec(
            methodClass: SubtractMethod::class,
            requestType: 'POST',
            methodName: 'subtract',
            requestMetadata: new RequestMetadata(
                request: SubtractRequest::class,
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
    }

    public function testSingleCallProducesRequestAndResponsePair(): void
    {
        $this->executeControllerTest(
            [
                'jsonrpc' => '2.0',
                'method' => 'subtract',
                'params' => [42, 23],
                'id' => 1,
            ],
            $this->subtractMethodSpec(),
        );

        self::assertCount(2, $this->sink->records);
        self::assertStringContainsString('Request: [subtract]', $this->sink->records[0]['message']);
        self::assertStringContainsString('Response: [subtract]', $this->sink->records[1]['message']);

        // Same context_id in both records (the pairing).
        $reqCtx = $this->sink->records[0]['context']['context_id'];
        $resCtx = $this->sink->records[1]['context']['context_id'];
        self::assertSame($reqCtx, $resCtx);
        self::assertNotEmpty($reqCtx);
    }

    public function testBatchProducesNPairsWithDistinctContextIds(): void
    {
        $this->executeControllerTest(
            [
                ['jsonrpc' => '2.0', 'method' => 'subtract', 'params' => [10, 1], 'id' => 'a'],
                ['jsonrpc' => '2.0', 'method' => 'subtract', 'params' => [20, 2], 'id' => 'b'],
                ['jsonrpc' => '2.0', 'method' => 'subtract', 'params' => [30, 3], 'id' => 'c'],
            ],
            $this->subtractMethodSpec(),
        );

        self::assertCount(6, $this->sink->records);

        $contextIds = array_map(static fn (array $r) => $r['context']['context_id'], $this->sink->records);
        self::assertCount(3, array_unique($contextIds), 'Each RPC call must have its own context_id');

        // Records 0/1 share contextId of first call, 2/3 of second, 4/5 of third.
        self::assertSame($contextIds[0], $contextIds[1]);
        self::assertSame($contextIds[2], $contextIds[3]);
        self::assertSame($contextIds[4], $contextIds[5]);
        self::assertNotSame($contextIds[0], $contextIds[2]);
    }

    public function testParseErrorIsLoggedViaRawRequest(): void
    {
        $this->setValidateMethodExpectation('any');
        $this->executeControllerTest(
            'this is not valid JSON',
            $this->subtractMethodSpec(),
        );

        self::assertCount(2, $this->sink->records);
        self::assertStringContainsString('Request: [unknown]', $this->sink->records[0]['message']);
        self::assertStringContainsString('[unparseable body,', $this->sink->records[0]['message']);
        self::assertStringContainsString('Response: [unknown]', $this->sink->records[1]['message']);

        $reqCtx = $this->sink->records[0]['context']['context_id'];
        $resCtx = $this->sink->records[1]['context']['context_id'];
        self::assertSame($reqCtx, $resCtx);
    }

    public function testDisabledLoggingProducesNoRecords(): void
    {
        // override the override: use Null logger explicitly
        $this->callLoggerOverride = new \OV\JsonRPCAPIBundle\Core\Logging\NullJsonRpcCallLogger();

        $this->executeControllerTest(
            [
                'jsonrpc' => '2.0',
                'method' => 'subtract',
                'params' => [42, 23],
                'id' => 1,
            ],
            $this->subtractMethodSpec(),
        );

        self::assertCount(0, $this->sink->records);
    }

    public function testMaskingHidesConfiguredKeys(): void
    {
        $this->callLoggerOverride = $this->makeCallLogger($this->sink, ['~^token$~i']);

        // Place `token` at the top-level RPC payload so the masker redacts it without
        // confusing SubtractMethod (which expects positional array params).
        $this->executeControllerTest(
            [
                'jsonrpc' => '2.0',
                'method' => 'subtract',
                'params' => [42, 23],
                'token' => 'super-secret',
                'id' => 1,
            ],
            $this->subtractMethodSpec(),
        );

        self::assertStringContainsString('"token":"***"', $this->sink->records[0]['message']);
        self::assertStringNotContainsString('super-secret', $this->sink->records[0]['message']);
    }

    public function testCustomFormatterOverridesViaTestSetup(): void
    {
        $customFormatter = new class implements JsonRpcLogFormatterInterface {
            public function format(JsonRpcLogEntry $entry): FormattedLogEntry {
                return new FormattedLogEntry(
                    message: sprintf('[%s] %s -- %s', $entry->contextId, $entry->direction->value, $entry->body),
                    context: ['method' => $entry->method ?? 'unknown'],
                    level: LogLevel::INFO,
                );
            }
        };

        $this->callLoggerOverride = new JsonRpcCallLogger(
            logger: $this->sink,
            formatter: $customFormatter,
            masker: new SensitiveDataMasker([], '***', new NullLogger()),
            contextIdGenerator: new UuidContextIdGenerator(),
            maxBodyLength: 0,
            skipPlainResponses: true,
        );

        $this->executeControllerTest(
            [
                'jsonrpc' => '2.0',
                'method' => 'subtract',
                'params' => [42, 23],
                'id' => 1,
            ],
            $this->subtractMethodSpec(),
        );

        self::assertCount(2, $this->sink->records);
        // Custom format: starts with `[contextId]`, not `Request:`
        self::assertStringStartsWith('[', $this->sink->records[0]['message']);
        self::assertStringContainsString(' request -- ', $this->sink->records[0]['message']);
        self::assertStringContainsString(' response -- ', $this->sink->records[1]['message']);
    }
}
