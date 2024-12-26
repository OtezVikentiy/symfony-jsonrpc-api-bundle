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
use OV\JsonRPCAPIBundle\RPC\V1\TestCallback\Request;
use OV\JsonRPCAPIBundle\RPC\V1\TestCallback\Response;

#[JsonRPCAPI(methodName: 'testCallback', type: 'POST', ignoreInSwagger: true)]
final class TestCallbackMethod extends AbstractMethod
{
    public function call(Request $request): Response
    {
        //... do some api logic here and return SubtractResponse
        //... use this class as any other service in Symfony

        return new Response($request->getTitle());
    }
}