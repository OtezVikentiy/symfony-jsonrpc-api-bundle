<?php

namespace OV\JsonRPCAPIBundle\Core\Services\RequestHandler;

use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;

class SingleBatchStrategy implements HandleBatchInterface
{
    public function handleBatch(array $batch, int $version, string $methodType, callable $batchProcessor): OvResponseInterface
    {
        $response = $batchProcessor($batch, $version, $methodType);

        if (is_null($response)) {
            return new JsonResponse($response);
        }

        return $response;
    }
}