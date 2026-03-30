<?php

namespace OV\JsonRPCAPIBundle\Tests\Core\Services\RequestHandler;

use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler\MultiBatchStrategy;
use PHPUnit\Framework\TestCase;

final class MultiBatchStrategyTest extends TestCase
{
    public function testHandleBatchProcessesMultipleItems(): void
    {
        $strategy = new MultiBatchStrategy();

        $batch = [
            ['jsonrpc' => '2.0', 'method' => 'sum', 'id' => '1'],
            ['jsonrpc' => '2.0', 'method' => 'subtract', 'id' => '2'],
        ];

        $callCount = 0;
        $callback = function (array $item) use (&$callCount) {
            $callCount++;
            $data = json_encode(['jsonrpc' => '2.0', 'result' => $callCount, 'id' => $item['id']]);
            return new JsonResponse(data: $data, json: true);
        };

        $result = $strategy->handleBatch($batch, 1, 'POST', $callback);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(2, $callCount);

        $content = json_decode($result->getContent(), true);
        $this->assertCount(2, $content);
        $this->assertEquals('1', $content[0]['id']);
        $this->assertEquals('2', $content[1]['id']);
    }

    public function testHandleBatchSkipsNullResponses(): void
    {
        $strategy = new MultiBatchStrategy();

        $batch = [
            ['jsonrpc' => '2.0', 'method' => 'sum', 'id' => '1'],
            ['jsonrpc' => '2.0', 'method' => 'notify'],
            ['jsonrpc' => '2.0', 'method' => 'subtract', 'id' => '2'],
        ];

        $callback = function (array $item) {
            if (!isset($item['id'])) {
                return null;
            }
            $data = json_encode(['jsonrpc' => '2.0', 'result' => 'ok', 'id' => $item['id']]);
            return new JsonResponse(data: $data, json: true);
        };

        $result = $strategy->handleBatch($batch, 1, 'POST', $callback);

        $content = json_decode($result->getContent(), true);
        $this->assertCount(2, $content);
    }

    public function testHandleBatchAllNullReturnsEmptyJsonResponse(): void
    {
        $strategy = new MultiBatchStrategy();

        $batch = [
            ['jsonrpc' => '2.0', 'method' => 'notify1'],
            ['jsonrpc' => '2.0', 'method' => 'notify2'],
        ];

        $callback = function () {
            return null;
        };

        $result = $strategy->handleBatch($batch, 1, 'POST', $callback);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertInstanceOf(OvResponseInterface::class, $result);
    }

    public function testHandleBatchWithSingleItem(): void
    {
        $strategy = new MultiBatchStrategy();

        $batch = [
            ['jsonrpc' => '2.0', 'method' => 'test', 'id' => '1'],
        ];

        $callback = function (array $item) {
            $data = json_encode(['jsonrpc' => '2.0', 'result' => 42, 'id' => $item['id']]);
            return new JsonResponse(data: $data, json: true);
        };

        $result = $strategy->handleBatch($batch, 1, 'POST', $callback);

        $content = json_decode($result->getContent(), true);
        $this->assertCount(1, $content);
        $this->assertEquals(42, $content[0]['result']);
    }
}
