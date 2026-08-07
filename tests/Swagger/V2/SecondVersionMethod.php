<?php

namespace OV\JsonRPCAPIBundle\Tests\Swagger\V2;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\Tests\Swagger\PlainRequest;
use OV\JsonRPCAPIBundle\Tests\Swagger\VisibleResponse;

#[JsonRPCAPI(methodName: 'secondVersion', type: 'POST', version: 2)]
final class SecondVersionMethod
{
    public function call(PlainRequest $request): VisibleResponse
    {
        return new VisibleResponse();
    }
}
