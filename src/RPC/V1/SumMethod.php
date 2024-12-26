<?php
/** @noinspection PhpUnused */

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
use OV\JsonRPCAPIBundle\RPC\V1\Sum\SumRequest;
use OV\JsonRPCAPIBundle\RPC\V1\Sum\SumResponse;

#[JsonRPCAPI(methodName: 'sum', type: 'POST', ignoreInSwagger: true)]
final class SumMethod
{
    public function call(SumRequest $request): SumResponse
    {
        $sum = 0;
        foreach ($request->getParams() as $param) {
            $sum += (int)$param;
        }

        return new SumResponse($sum);
    }
}