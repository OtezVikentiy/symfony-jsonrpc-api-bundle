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
            $decoded = json_decode($response->getContent(), true);
            if ($decoded !== null || json_last_error() === JSON_ERROR_NONE) {
                $responsesContent[] = $decoded;
            }
        }

        if (empty($responsesContent)) {
            return new JsonResponse(data: '', json: true);
        }

        return new JsonResponse(data: $responsesContent);
    }
}