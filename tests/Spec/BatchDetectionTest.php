<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Spec;

use OV\JsonRPCAPIBundle\Core\Services\RequestHandler\BatchStrategyFactory;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler\MultiBatchStrategy;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler\SingleBatchStrategy;
use PHPUnit\Framework\TestCase;

final class BatchDetectionTest extends TestCase
{
    public function testListWithInvalidFirstElementIsStillABatch(): void
    {
        $strategy = BatchStrategyFactory::createBatchStrategy([
            ['foo' => 'boo'],
            ['jsonrpc' => '2.0', 'method' => 'sum', 'id' => 1],
        ]);

        $this->assertInstanceOf(MultiBatchStrategy::class, $strategy);
    }

    public function testListOfScalarsIsABatch(): void
    {
        $this->assertInstanceOf(MultiBatchStrategy::class, BatchStrategyFactory::createBatchStrategy([1, 2, 3]));
    }

    public function testSingleRequestObjectIsNotABatch(): void
    {
        $strategy = BatchStrategyFactory::createBatchStrategy(['jsonrpc' => '2.0', 'method' => 'sum', 'id' => 1]);

        $this->assertInstanceOf(SingleBatchStrategy::class, $strategy);
    }

    public function testEmptyArrayIsNotABatch(): void
    {
        $this->assertInstanceOf(SingleBatchStrategy::class, BatchStrategyFactory::createBatchStrategy([]));
    }
}
