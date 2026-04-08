<?php

namespace OV\JsonRPCAPIBundle\RPC\V1\Nested;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\RPC\V1\Nested\Multiply\MultiplyRequest;
use OV\JsonRPCAPIBundle\RPC\V1\Nested\Multiply\MultiplyResponse;

#[JsonRPCAPI(methodName: 'multiply', type: 'POST', ignoreInSwagger: true)]
final class MultiplyMethod
{
    public function call(MultiplyRequest $request): MultiplyResponse
    {
        $res = $request->getParams()[0] * $request->getParams()[1];

        return new MultiplyResponse($res);
    }
}
