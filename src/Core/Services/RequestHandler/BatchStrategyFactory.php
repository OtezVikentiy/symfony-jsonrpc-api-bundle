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
            isset($data[0])
            && isset($data[1])
            && is_array($data[0])
            && is_array($data[1])
            && array_key_exists('jsonrpc', $data[0])
            && array_key_exists('jsonrpc', $data[1])
        ) {
            return true;
        }

        return false;
    }
}