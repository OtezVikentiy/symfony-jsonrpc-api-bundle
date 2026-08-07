<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Logging;

use OV\JsonRPCAPIBundle\Core\Logging\ContextIdGeneratorInterface;
use OV\JsonRPCAPIBundle\Core\Logging\DefaultJsonRpcLogFormatter;
use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcCallLogger;
use OV\JsonRPCAPIBundle\Core\Logging\SensitiveDataMaskerInterface;
use OV\JsonRPCAPIBundle\Tests\Fixtures\TestLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

/**
 * logRawRequest() runs only after the request has already been refused, which makes it the path an
 * attacker aims at. Decoding a body that the size limit has just rejected, walking it to mask keys
 * and encoding it back turns the limit into an amplifier - the work is proportional to the payload
 * the server declined to process, and none of it can reach the log line anyway once max_body_length
 * truncates the result.
 *
 * The assertions here count masker calls rather than measure time: a timing threshold would be
 * flaky, while "did the expensive traversal happen at all" is exactly the property at stake.
 */
final class RejectedBodyLoggingTest extends TestCase
{
    private const MAX_BODY_LENGTH = 8192;

    public function testBodyLargerThanTheLogLineIsNeverDecodedOrMasked(): void
    {
        $masker = $this->countingMasker();
        $logger = $this->makeLogger(new TestLogger(), $masker);

        $logger->logRawRequest($this->jsonOfAtLeast(self::MAX_BODY_LENGTH * 4));

        self::assertSame(0, $masker->calls, 'a body that cannot fit the log line must not be traversed');
    }

    public function testBodyThatFitsTheLogLineIsStillDecodedAndMasked(): void
    {
        $masker = $this->countingMasker();
        $logger = $this->makeLogger(new TestLogger(), $masker);

        $logger->logRawRequest('{"jsonrpc":"2.0","method":"x","params":{"password":"hunter2"},"id":1}');

        self::assertSame(1, $masker->calls, 'ordinary bodies must keep being masked');
    }

    public function testOversizeBodyIsRecordedByItsSize(): void
    {
        $sink = new TestLogger();
        $body = $this->jsonOfAtLeast(self::MAX_BODY_LENGTH * 4);

        $this->makeLogger($sink, $this->countingMasker())->logRawRequest($body);

        self::assertStringContainsString(sprintf('[unparseable body, %d bytes]', strlen($body)), json_encode($sink->records));
    }

    /**
     * With truncation switched off there is no smaller bound to fall back to, so the payload limit
     * is the only one left and a body within it is still decoded.
     */
    public function testUntruncatedLoggingFallsBackToThePayloadLimit(): void
    {
        $masker = $this->countingMasker();
        $logger = $this->makeLogger(new TestLogger(), $masker, maxBodyLength: 0);

        $logger->logRawRequest($this->jsonOfAtLeast(self::MAX_BODY_LENGTH * 4));

        self::assertSame(1, $masker->calls);
    }

    private function jsonOfAtLeast(int $bytes): string
    {
        $payload = '{';
        $index = 0;

        while (strlen($payload) < $bytes) {
            $payload .= ($index > 0 ? ',' : '') . sprintf('"key%d":"value"', $index);
            ++$index;
        }

        return $payload . '}';
    }

    private function countingMasker(): SensitiveDataMaskerInterface
    {
        return new class implements SensitiveDataMaskerInterface {
            public int $calls = 0;

            public function mask(array $data): array
            {
                ++$this->calls;

                return $data;
            }
        };
    }

    private function makeLogger(
        TestLogger $sink,
        SensitiveDataMaskerInterface $masker,
        int $maxBodyLength = self::MAX_BODY_LENGTH,
    ): JsonRpcCallLogger {
        $generator = new class implements ContextIdGeneratorInterface {
            public function generate(): string
            {
                return 'ctx-1';
            }
        };

        return new JsonRpcCallLogger(
            logger: $sink,
            formatter: new DefaultJsonRpcLogFormatter(LogLevel::INFO, LogLevel::INFO, LogLevel::WARNING),
            masker: $masker,
            contextIdGenerator: $generator,
            maxBodyLength: $maxBodyLength,
            skipPlainResponses: true,
        );
    }
}
