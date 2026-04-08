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
use OV\JsonRPCAPIBundle\RPC\V1\GetFilteredData\Request;
use OV\JsonRPCAPIBundle\RPC\V1\GetFilteredData\Response;

#[JsonRPCAPI(methodName: 'GetFilteredData', type: 'POST', ignoreInSwagger: true)]
final class GetFilteredData
{
    public function call(Request $request): Response
    {
        return new Response([
            $request->getFilter()->getId(),
            $request->getFilter()->getTitle(),
            $request->getFilter()->isFinished()
        ]);
    }
}