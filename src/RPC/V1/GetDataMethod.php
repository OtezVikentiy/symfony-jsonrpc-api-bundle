<?php
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\Core\ApiMethodInterface;
use OV\JsonRPCAPIBundle\RPC\V1\GetData\GetDataRequest;
use OV\JsonRPCAPIBundle\RPC\V1\GetData\GetDataResponse;

#[JsonRPCAPI(methodName: 'get_data', type: 'POST', ignoreInSwagger: true)]
class GetDataMethod implements ApiMethodInterface
{
    public function call(GetDataRequest $request): GetDataResponse
    {
        //... do some api logic here and return SubtractResponse
        //... use this class as any other service in Symfony

        return new GetDataResponse(['hello', 5]);
    }
}