<?php

namespace OV\JsonRPCAPIBundle\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\RPC\V1\Handler\BareParamsRequest;

#[JsonRPCAPI(methodName: 'bareParams', type: 'POST', version: 1, ignoreInSwagger: true)]
final class BareParamsMethod
{
    public function call(BareParamsRequest $request): array
    {
        return ['seen' => $request->getParams()];
    }
}
