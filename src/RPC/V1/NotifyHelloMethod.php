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
use OV\JsonRPCAPIBundle\RPC\V1\NotifyHello\NotifyHelloRequest;
use OV\JsonRPCAPIBundle\RPC\V1\NotifyHello\NotifyHelloResponse;

#[JsonRPCAPI(methodName: 'notify_hello', type: 'POST', ignoreInSwagger: true)]
final class NotifyHelloMethod
{
    /** @noinspection PhpUnusedParameterInspection */
    public function call(NotifyHelloRequest $request): NotifyHelloResponse
    {
        //... do some api logic here and return SubtractResponse
        //... use this class as any other service in Symfony

        return new NotifyHelloResponse();
    }
}