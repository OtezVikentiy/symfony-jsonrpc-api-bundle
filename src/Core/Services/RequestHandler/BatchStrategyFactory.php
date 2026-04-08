<?php

namespace OV\JsonRPCAPIBundle\Core\Services\RequestHandler;

final class BatchStrategyFactory
{
    public static function createBatchStrategy(array $data): HandleBatchInterface
    {
        return self::isBatch($data) ? new MultiBatchStrategy() : new SingleBatchStrategy();
    }

    private static function isBatch(array $data): bool
    {
        return is_array($data)
            && array_key_exists(0, $data)
            && is_array($data[0])
            && array_key_exists('jsonrpc', $data[0])
            && array_key_exists('method', $data[0]);
    }
}
