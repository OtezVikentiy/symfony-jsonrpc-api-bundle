<?php

namespace OV\JsonRPCAPIBundle\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\RPC\V1\Handler\RequiredCtorCollectionRequest;

#[JsonRPCAPI(methodName: 'requiredCtorCollecting', type: 'POST', version: 1, ignoreInSwagger: true)]
final class RequiredCtorCollectingMethod
{
    public function call(RequiredCtorCollectionRequest $request): array
    {
        return ['count' => count($request->getTags())];
    }
}
