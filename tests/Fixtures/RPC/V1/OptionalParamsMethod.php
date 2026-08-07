<?php

namespace OV\JsonRPCAPIBundle\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\RPC\V1\OptionalParams\OptionalParamsRequest;

#[JsonRPCAPI(methodName: 'optionalParams', type: 'POST', version: 1, ignoreInSwagger: true)]
final class OptionalParamsMethod
{
    public function call(OptionalParamsRequest $request): array
    {
        return ['other' => $request->getOther(), 'params' => $request->getParams()];
    }
}
