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
use OV\JsonRPCAPIBundle\RPC\V1\Test\TestRequest;
use OV\JsonRPCAPIBundle\RPC\V1\Test\TestResponse;

#[JsonRPCAPI(
    methodName: 'test',
    type: 'POST',
    version: 1,
    summary: 'Test method summary',
    tags: ['test'],
    description: 'Test method description',
    ignoreInSwagger: true,
    roles: ['ROLE_PENTESTER']
)]
final class TestMethod
{
    public function call(TestRequest $request): TestResponse
    {
        //... do some api logic here and return SubtractResponse
        //... use this class as any other service in Symfony

        return new TestResponse($request->getTitle());
    }
}