<?php

namespace OV\JsonRPCAPIBundle\Core\Services\RequestHandler;

use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;

final class MultiBatchStrategy implements HandleBatchInterface
{
    public function handleBatch(array $batch, int $version, string $methodType, callable $batchProcessor): OvResponseInterface
    {
        $jsonParts = [];

        foreach ($batch as $item) {
            $response = $batchProcessor($item, $version, $methodType);
            if (is_null($response)) {
                continue;
            }
            $content = $response->getContent();
            if ($content !== '' && $content !== false) {
                $jsonParts[] = $content;
            }
        }

        if (empty($jsonParts)) {
            return new JsonResponse(data: '', json: true);
        }

        $json = '[' . implode(',', $jsonParts) . ']';

        return new JsonResponse(data: $json, json: true);
    }
}
