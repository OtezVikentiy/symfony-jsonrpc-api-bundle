<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\RPC\V1\Throwing\ThrowingRequest;
use RuntimeException;

#[JsonRPCAPI(methodName: 'throwing', type: 'POST', ignoreInSwagger: true)]
final class ThrowingMethod
{
    private const FAILURE_MESSAGE = 'Database connection to 10.0.0.5 refused for user app_rw';

    public function call(ThrowingRequest $request): never
    {
        throw new RuntimeException(self::FAILURE_MESSAGE);
    }
}
