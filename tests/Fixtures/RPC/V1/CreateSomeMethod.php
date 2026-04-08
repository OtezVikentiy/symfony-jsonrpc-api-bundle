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
use OV\JsonRPCAPIBundle\RPC\V1\CreateSome\Request;
use OV\JsonRPCAPIBundle\RPC\V1\CreateSome\Response;
use OV\JsonRPCAPIBundle\RPC\V1\CreateSome\Token;

#[JsonRPCAPI(methodName: 'CreateSomeMethod', type: 'POST', ignoreInSwagger: true)]
final class CreateSomeMethod
{
    public function call(Request $request): Response
    {
        $strings = [];
        /** @var Token $token */
        foreach ($request->getTokens() as $token) {
            $strings[] = sprintf('%s_%s_%s', $token->getName(), $token->getValue(), $token->getSummary());
        }

        return (new Response())->setResponseString(implode(' ||| ', $strings));
    }
}