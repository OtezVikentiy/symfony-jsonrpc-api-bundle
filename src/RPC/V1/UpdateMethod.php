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
use OV\JsonRPCAPIBundle\RPC\V1\Update\UpdateRequest;
use OV\JsonRPCAPIBundle\RPC\V1\Update\UpdateResponse;

#[JsonRPCAPI(methodName: 'update', type: 'PUT', ignoreInSwagger: true)]
class UpdateMethod
{
    public function call(UpdateRequest $request): UpdateResponse
    {
        // do some logic here

        return new UpdateResponse();
    }
}