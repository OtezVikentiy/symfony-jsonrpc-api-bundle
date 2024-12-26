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
use OV\JsonRPCAPIBundle\RPC\V1\Subtract2\Subtract2Request;
use OV\JsonRPCAPIBundle\RPC\V1\Subtract2\Subtract2Response;

#[JsonRPCAPI(methodName: 'subtract2', type: 'POST', ignoreInSwagger: true)]
final class Subtract2Method
{
    public function call(Subtract2Request $request): Subtract2Response
    {
        $res = $request->getMinuend() - $request->getSubtrahend();

        return new Subtract2Response($res);
    }
}