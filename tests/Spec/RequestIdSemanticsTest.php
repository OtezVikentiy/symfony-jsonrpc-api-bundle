<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Spec;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Request\BaseRequest;
use PHPUnit\Framework\TestCase;

final class RequestIdSemanticsTest extends TestCase
{
    public function testExplicitNullIdIsPresent(): void
    {
        $request = new BaseRequest(['jsonrpc' => '2.0', 'method' => 'm', 'id' => null]);

        $this->assertTrue($request->hasId());
        $this->assertNull($request->getId());
    }

    public function testMissingIdIsAbsent(): void
    {
        $request = new BaseRequest(['jsonrpc' => '2.0', 'method' => 'm']);

        $this->assertFalse($request->hasId());
        $this->assertNull($request->getId());
    }

    public function testScalarIdTypesAreAccepted(): void
    {
        foreach ([['id' => 'abc', 'expected' => 'abc'], ['id' => 7, 'expected' => 7], ['id' => 1.5, 'expected' => 1.5]] as $case) {
            $request = new BaseRequest(['jsonrpc' => '2.0', 'method' => 'm', 'id' => $case['id']]);

            $this->assertTrue($request->hasId());
            $this->assertSame($case['expected'], $request->getId());
        }
    }

    public function testArrayIdIsRejected(): void
    {
        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_REQUEST);

        new BaseRequest(['jsonrpc' => '2.0', 'method' => 'm', 'id' => [1, 2]]);
    }

    public function testObjectIdIsRejected(): void
    {
        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_REQUEST);

        new BaseRequest(['jsonrpc' => '2.0', 'method' => 'm', 'id' => ['a' => 1]]);
    }
}
