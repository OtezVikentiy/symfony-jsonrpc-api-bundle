<?php

namespace OV\JsonRPCAPIBundle\Tests\Core\Services\RequestHandler;

use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler\SingleBatchStrategy;
use PHPUnit\Framework\TestCase;

final class SingleBatchStrategyTest extends TestCase
{
    public function testHandleBatchCallsProcessorAndReturnsResult(): void
    {
        $strategy = new SingleBatchStrategy();
        $expectedResponse = new JsonResponse(['result' => 'ok']);

        $callback = function (array $batch, int $version, string $methodType) use ($expectedResponse) {
            $this->assertEquals(['jsonrpc' => '2.0', 'method' => 'test'], $batch);
            $this->assertEquals(1, $version);
            $this->assertEquals('POST', $methodType);
            return $expectedResponse;
        };

        $result = $strategy->handleBatch(
            ['jsonrpc' => '2.0', 'method' => 'test'],
            1,
            'POST',
            $callback
        );

        $this->assertSame($expectedResponse, $result);
    }

    public function testHandleBatchWithNullResponseReturnsJsonResponse(): void
    {
        $strategy = new SingleBatchStrategy();

        $callback = function () {
            return null;
        };

        $result = $strategy->handleBatch([], 1, 'POST', $callback);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertInstanceOf(OvResponseInterface::class, $result);
    }
}
