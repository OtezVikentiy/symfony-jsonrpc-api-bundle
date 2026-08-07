<?php

namespace OV\JsonRPCAPIBundle\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\RPC\V1\Handler\NullableParamsRequest;

#[JsonRPCAPI(methodName: 'nullableParams', type: 'POST', version: 1, ignoreInSwagger: true)]
final class NullableParamsMethod
{
    public function call(NullableParamsRequest $request): array
    {
        return ['seen' => $request->getParams()];
    }
}
