<?php

namespace OV\JsonRPCAPIBundle\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\RPC\V1\ObjectCollection\ObjectCollectionRequest;

#[JsonRPCAPI(methodName: 'objectCollection', type: 'POST', version: 1, ignoreInSwagger: true)]
final class ObjectCollectionMethod
{
    public function call(ObjectCollectionRequest $request): array
    {
        return ['count' => count($request->getItems()->all())];
    }
}
