<?php

namespace OV\JsonRPCAPIBundle\Core\Services\RequestHandler;

use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;

interface HandleBatchInterface
{
    public function handleBatch(array $batch, int $version, string $methodType, callable $batchProcessor): OvResponseInterface;
}