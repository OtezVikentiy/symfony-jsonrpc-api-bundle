<?php

namespace OV\JsonRPCAPIBundle\Tests;

use OV\JsonRPCAPIBundle\RPC\V1\test\TestRequest;
use PHPUnit\Framework\TestCase;

class TestMethodTest extends TestCase
{
    public function testCreateRequest()
    {
        $request = new TestRequest(1);

        $this->assertSame(1, $request->getId());
    }
}