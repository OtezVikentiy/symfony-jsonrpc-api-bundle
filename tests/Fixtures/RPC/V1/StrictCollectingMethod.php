<?php

namespace OV\JsonRPCAPIBundle\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\RPC\V1\Handler\StrictCollectionRequest;

#[JsonRPCAPI(methodName: 'strictCollecting', type: 'POST', version: 1, ignoreInSwagger: true)]
final class StrictCollectingMethod
{
    public function call(StrictCollectionRequest $request): array
    {
        return ['count' => count($request->getTags())];
    }
}
