<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Core\Services\RequestHandler;

use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;
use UnexpectedValueException;

final class MultiBatchStrategy implements HandleBatchInterface
{
    private const NON_JSON_BATCH_ELEMENT_MESSAGE = 'A batch element produced non-JSON content instead of a JSON-RPC response.';

    public function handleBatch(array $batch, int $version, string $methodType, callable $batchProcessor): OvResponseInterface
    {
        $items = [];

        foreach ($batch as $item) {
            $response = $batchProcessor($item, $version, $methodType);
            if (is_null($response)) {
                continue;
            }

            $content = $response->getContent();
            if ($content === '' || $content === false) {
                continue;
            }

            $decoded = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new UnexpectedValueException(self::NON_JSON_BATCH_ELEMENT_MESSAGE);
            }

            $items[] = $decoded;
        }

        if (empty($items)) {
            return new JsonResponse(data: '', json: true);
        }

        return new JsonResponse(data: $items, json: false);
    }
}
