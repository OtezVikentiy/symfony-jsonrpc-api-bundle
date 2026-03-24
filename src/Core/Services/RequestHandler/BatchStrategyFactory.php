<?php

namespace OV\JsonRPCAPIBundle\Core\Services\RequestHandler;

final class BatchStrategyFactory
{
    public static function createBatchStrategy(array $data): HandleBatchInterface
    {
        $strategy = new SingleBatchStrategy();
        if (BatchStrategyFactory::isBatch($data)) {
            $strategy = new MultiBatchStrategy();
        }

        return $strategy;
    }

    private static function isBatch(array $data): bool
    {
        if (
            is_array($data)
            && array_key_exists(0, $data)
            && is_array($data[0])
            && array_key_exists('jsonrpc', $data[0])
            && array_key_exists('method', $data[0])
        ) {
            return true;
        }

        return false;
    }
}