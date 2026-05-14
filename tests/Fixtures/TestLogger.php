<?php

namespace OV\JsonRPCAPIBundle\Tests\Fixtures;

use Psr\Log\AbstractLogger;

/**
 * Minimal PSR-3 test logger fixture: records all log entries so tests can assert on them.
 */
final class TestLogger extends AbstractLogger
{
    /** @var array<int, array{level: string, message: string, context: array<mixed>}> */
    public array $records = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = [
            'level'   => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    public function hasRecordsAtLevel(string $level): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] === $level) {
                return true;
            }
        }

        return false;
    }

    public function hasWarningRecords(): bool
    {
        return $this->hasRecordsAtLevel('warning');
    }

    public function hasErrorRecords(): bool
    {
        return $this->hasRecordsAtLevel('error');
    }
}
