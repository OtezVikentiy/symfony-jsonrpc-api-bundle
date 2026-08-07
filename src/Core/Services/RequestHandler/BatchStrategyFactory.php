<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Core\Services\RequestHandler;

/**
 * @internal
 */
final class BatchStrategyFactory
{
    public static function createBatchStrategy(array $data): HandleBatchInterface
    {
        return self::isBatch($data) ? new MultiBatchStrategy() : new SingleBatchStrategy();
    }

    private static function isBatch(array $data): bool
    {
        return $data !== [] && array_is_list($data);
    }
}
