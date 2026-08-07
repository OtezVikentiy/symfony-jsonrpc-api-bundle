<?php

namespace OV\JsonRPCAPIBundle\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\RPC\V1\Handler\ScalarCollectionRequest;

#[JsonRPCAPI(methodName: 'scalarCollecting', type: 'POST', version: 1, ignoreInSwagger: true)]
final class ScalarCollectingMethod
{
    public function call(ScalarCollectionRequest $request): array
    {
        return ['count' => count($request->getCodes())];
    }
}
