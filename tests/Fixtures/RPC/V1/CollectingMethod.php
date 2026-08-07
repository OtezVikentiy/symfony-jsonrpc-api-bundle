<?php

namespace OV\JsonRPCAPIBundle\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\RPC\V1\Handler\CollectionRequest;

/**
 * Keeps the hydrated request so a test can inspect what hydration actually produced - the provided
 * flags in particular, which no response can carry.
 */
#[JsonRPCAPI(methodName: 'collecting', type: 'POST', version: 1, ignoreInSwagger: true)]
final class CollectingMethod
{
    public static ?CollectionRequest $last = null;

    public function call(CollectionRequest $request): array
    {
        self::$last = $request;

        return ['count' => count($request->getTags())];
    }
}
