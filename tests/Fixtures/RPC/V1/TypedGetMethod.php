<?php

namespace OV\JsonRPCAPIBundle\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\RPC\V1\TypedGet\TypedGetRequest;

#[JsonRPCAPI(methodName: 'typedGet', type: 'GET', version: 1, ignoreInSwagger: true)]
final class TypedGetMethod
{
    public function call(TypedGetRequest $request): array
    {
        return ['ok' => true];
    }
}
