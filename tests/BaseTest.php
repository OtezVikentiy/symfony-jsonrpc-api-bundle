<?php
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\Tests;

use OV\JsonRPCAPIBundle\RPC\V1\Test\TestRequest;
use PHPUnit\Framework\TestCase;

class BaseTest extends TestCase
{
    public function testCreateRequest()
    {
        $request = new TestRequest(1);

        $this->assertSame(1, $request->getId());
    }
}