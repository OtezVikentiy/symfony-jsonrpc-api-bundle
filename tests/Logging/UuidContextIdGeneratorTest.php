<?php

namespace OV\JsonRPCAPIBundle\Tests\Logging;

use OV\JsonRPCAPIBundle\Core\Logging\UuidContextIdGenerator;
use PHPUnit\Framework\TestCase;

final class UuidContextIdGeneratorTest extends TestCase
{
    public function testGenerateReturnsValidUuidV4(): void
    {
        $generator = new UuidContextIdGenerator();
        $id = $generator->generate();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $id,
        );
    }

    public function testGenerateProducesUniqueIds(): void
    {
        $generator = new UuidContextIdGenerator();
        $ids = [];
        for ($i = 0; $i < 10_000; $i++) {
            $ids[$generator->generate()] = true;
        }

        self::assertCount(10_000, $ids);
    }
}
