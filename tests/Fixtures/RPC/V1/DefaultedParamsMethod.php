<?php

namespace OV\JsonRPCAPIBundle\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\RPC\V1\DefaultedParams\DefaultedParamsRequest;

#[JsonRPCAPI(methodName: 'defaultedParams', type: 'POST', version: 1, ignoreInSwagger: true)]
final class DefaultedParamsMethod
{
    public function call(DefaultedParamsRequest $request): array
    {
        return ['sum' => array_sum($request->getParams()), 'count' => count($request->getParams())];
    }
}
