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
use OV\JsonRPCAPIBundle\RPC\V1\Subtract\SubtractRequest;
use OV\JsonRPCAPIBundle\RPC\V1\Subtract\SubtractResponse;

#[JsonRPCAPI(methodName: 'subtract', type: 'POST', ignoreInSwagger: true)]
class SubtractMethod
{
    public function call(SubtractRequest $request): SubtractResponse
    {
        $res = $request->getParams()[0] - $request->getParams()[1];

        return new SubtractResponse($res);
    }
}