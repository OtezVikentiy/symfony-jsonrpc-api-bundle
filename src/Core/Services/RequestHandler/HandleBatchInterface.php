<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Core\Services\RequestHandler;

use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;

interface HandleBatchInterface
{
    /**
     * @param callable(mixed, int, string): ?OvResponseInterface $batchProcessor
     */
    public function handleBatch(array $batch, int $version, string $methodType, callable $batchProcessor): OvResponseInterface;
}
