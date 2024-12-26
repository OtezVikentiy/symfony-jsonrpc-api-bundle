<?php

namespace OV\JsonRPCAPIBundle\Core\Services\RequestHandler;

use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;

final class MultiBatchStrategy implements HandleBatchInterface
{
    public function handleBatch(array $batch, int $version, string $methodType, callable $batchProcessor): OvResponseInterface
    {
        $responsesContent = [];

        foreach ($batch as $item) {
            $response = $batchProcessor($item, $version, $methodType);
            if (is_null($response)) {
                continue;
            }
            $responsesContent[] = json_decode($response->getContent(), true);
        }

        if (empty($responsesContent)) {
            return new JsonResponse();
        }

        return new JsonResponse(data: $responsesContent);
    }
}