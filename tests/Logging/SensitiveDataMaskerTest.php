<?php

namespace OV\JsonRPCAPIBundle\Tests\Logging;

use OV\JsonRPCAPIBundle\Core\Logging\SensitiveDataMasker;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use OV\JsonRPCAPIBundle\Tests\Fixtures\TestLogger;

final class SensitiveDataMaskerTest extends TestCase
{
    public function testMasksTopLevelKeyMatchingPattern(): void
    {
        $masker = new SensitiveDataMasker(['~^password$~i'], '***', new NullLogger());

        $result = $masker->mask(['user' => 'alice', 'password' => 'p4ss']);

        self::assertSame(['user' => 'alice', 'password' => '***'], $result);
    }

    public function testMasksNestedKeysRecursively(): void
    {
        $masker = new SensitiveDataMasker(['~^token$~i'], '***', new NullLogger());

        $result = $masker->mask([
            'params' => [
                'auth' => ['token' => 'abc', 'kind' => 'bearer'],
            ],
        ]);

        self::assertSame(
            ['params' => ['auth' => ['token' => '***', 'kind' => 'bearer']]],
            $result,
        );
    }

    public function testMasksArrayOfObjects(): void
    {
        $masker = new SensitiveDataMasker(['~^secret$~i'], '***', new NullLogger());

        $result = $masker->mask([
            'items' => [
                ['name' => 'a', 'secret' => 's1'],
                ['name' => 'b', 'secret' => 's2'],
            ],
        ]);

        self::assertSame(
            ['items' => [['name' => 'a', 'secret' => '***'], ['name' => 'b', 'secret' => '***']]],
            $result,
        );
    }

    public function testReplacesEntireSubtreeWhenKeyMatches(): void
    {
        $masker = new SensitiveDataMasker(['~^credentials$~i'], '***', new NullLogger());

        $result = $masker->mask([
            'credentials' => ['login' => 'u', 'password' => 'p', 'meta' => ['x' => 1]],
        ]);

        self::assertSame(['credentials' => '***'], $result);
    }

    public function testPassesThroughWhenNoPatternsConfigured(): void
    {
        $masker = new SensitiveDataMasker([], '***', new NullLogger());

        $input = ['a' => 1, 'b' => ['c' => 2]];
        self::assertSame($input, $masker->mask($input));
    }

    public function testHandlesEmptyArray(): void
    {
        $masker = new SensitiveDataMasker(['~^x$~'], '***', new NullLogger());

        self::assertSame([], $masker->mask([]));
    }

    public function testInvalidRegexIsSkippedAndWarned(): void
    {
        $logger = new TestLogger();
        $masker = new SensitiveDataMasker(['~^password$~i', 'invalid('], '***', $logger);

        $result = $masker->mask(['password' => 'x', 'other' => 'y']);

        self::assertSame(['password' => '***', 'other' => 'y'], $result);
        self::assertTrue($logger->hasWarningRecords());
        self::assertSame('invalid(', $logger->records[0]['context']['pattern']);
    }

    public function testInvalidRegexWarnsOnlyOnce(): void
    {
        $logger = new TestLogger();
        $masker = new SensitiveDataMasker(['invalid('], '***', $logger);

        $masker->mask(['a' => 1]);
        $masker->mask(['b' => 2]);
        $masker->mask(['c' => 3]);

        $warningsCount = count(array_filter(
            $logger->records,
            static fn (array $r) => $r['level'] === 'warning',
        ));
        self::assertSame(1, $warningsCount);
    }

    public function testCustomPlaceholder(): void
    {
        $masker = new SensitiveDataMasker(['~^token$~'], '[REDACTED]', new NullLogger());

        self::assertSame(['token' => '[REDACTED]'], $masker->mask(['token' => 'x']));
    }
}
