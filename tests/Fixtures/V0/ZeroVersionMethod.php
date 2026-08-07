<?php

namespace OV\JsonRPCAPIBundle\Tests\Fixtures\V0;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;

/**
 * A namespace segment of V0: it matches the version pattern but parses to zero, which is no API
 * version at all.
 */
#[JsonRPCAPI(methodName: 'zeroVersion', type: 'POST', ignoreInSwagger: true)]
final class ZeroVersionMethod
{
    public function call(): array
    {
        return [];
    }
}
