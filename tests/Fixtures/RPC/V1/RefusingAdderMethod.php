<?php

namespace OV\JsonRPCAPIBundle\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\RPC\V1\Handler\RefusingAdderRequest;

#[JsonRPCAPI(methodName: 'refusingAdder', type: 'POST', version: 1, ignoreInSwagger: true)]
final class RefusingAdderMethod
{
    public function call(RefusingAdderRequest $request): array
    {
        return ['count' => count($request->getTags())];
    }
}
