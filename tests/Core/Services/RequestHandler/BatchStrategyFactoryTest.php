<?php

namespace OV\JsonRPCAPIBundle\Tests\Core\Services\RequestHandler;

use OV\JsonRPCAPIBundle\Core\Services\RequestHandler\BatchStrategyFactory;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler\MultiBatchStrategy;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler\SingleBatchStrategy;
use PHPUnit\Framework\TestCase;

final class BatchStrategyFactoryTest extends TestCase
{
    public function testSingleRequestReturnsSingleStrategy(): void
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'id' => '1',
        ];

        $strategy = BatchStrategyFactory::createBatchStrategy($data);

        $this->assertInstanceOf(SingleBatchStrategy::class, $strategy);
    }

    public function testBatchRequestReturnsMultiStrategy(): void
    {
        $data = [
            [
                'jsonrpc' => '2.0',
                'method' => 'sum',
                'params' => [1, 2],
                'id' => '1',
            ],
            [
                'jsonrpc' => '2.0',
                'method' => 'subtract',
                'params' => [5, 3],
                'id' => '2',
            ],
        ];

        $strategy = BatchStrategyFactory::createBatchStrategy($data);

        $this->assertInstanceOf(MultiBatchStrategy::class, $strategy);
    }

    public function testEmptyArrayReturnsSingleStrategy(): void
    {
        $strategy = BatchStrategyFactory::createBatchStrategy([]);

        $this->assertInstanceOf(SingleBatchStrategy::class, $strategy);
    }

    public function testArrayWithoutJsonrpcReturnsSingleStrategy(): void
    {
        $data = [
            ['foo' => 'bar'],
        ];

        $strategy = BatchStrategyFactory::createBatchStrategy($data);

        $this->assertInstanceOf(SingleBatchStrategy::class, $strategy);
    }

    public function testArrayWithoutMethodReturnsSingleStrategy(): void
    {
        $data = [
            ['jsonrpc' => '2.0'],
        ];

        $strategy = BatchStrategyFactory::createBatchStrategy($data);

        $this->assertInstanceOf(SingleBatchStrategy::class, $strategy);
    }

    public function testNonArrayFirstElementReturnsSingleStrategy(): void
    {
        $data = ['some_string'];

        $strategy = BatchStrategyFactory::createBatchStrategy($data);

        $this->assertInstanceOf(SingleBatchStrategy::class, $strategy);
    }

    public function testBatchWithSingleItemReturnsMultiStrategy(): void
    {
        $data = [
            [
                'jsonrpc' => '2.0',
                'method' => 'test',
                'id' => '1',
            ],
        ];

        $strategy = BatchStrategyFactory::createBatchStrategy($data);

        $this->assertInstanceOf(MultiBatchStrategy::class, $strategy);
    }
}
