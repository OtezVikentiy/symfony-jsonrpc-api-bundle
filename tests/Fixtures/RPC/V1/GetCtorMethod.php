<?php

namespace OV\JsonRPCAPIBundle\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\RPC\V1\Handler\GetCtorRequest;

#[JsonRPCAPI(methodName: 'getCtor', type: 'GET', version: 1, ignoreInSwagger: true)]
final class GetCtorMethod
{
    public function call(GetCtorRequest $request): array
    {
        return ['id' => $request->getId(), 'depth' => $request->getInner()->getDepth()];
    }
}
