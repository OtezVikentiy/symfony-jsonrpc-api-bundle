<?php

namespace OV\JsonRPCAPIBundle\Tests\Core\Request;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Request\BaseRequest;
use PHPUnit\Framework\TestCase;

final class BaseRequestTest extends TestCase
{
    public function testValidRequestWithAllFields(): void
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'subtract',
            'params' => [42, 23],
            'id' => '1',
        ];

        $request = new BaseRequest($data);

        $this->assertEquals('2.0', $request->getJsonrpc());
        $this->assertEquals('subtract', $request->getMethod());
        $this->assertEquals([42, 23], $request->getParams());
        $this->assertEquals('1', $request->getId());
    }

    public function testValidRequestWithoutParams(): void
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'get_data',
            'id' => '9',
        ];

        $request = new BaseRequest($data);

        $this->assertEquals('2.0', $request->getJsonrpc());
        $this->assertEquals('get_data', $request->getMethod());
        $this->assertEquals([], $request->getParams());
        $this->assertEquals('9', $request->getId());
    }

    public function testNotificationWithoutId(): void
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'notify_hello',
            'params' => [1, 2, 3],
        ];

        $request = new BaseRequest($data);

        $this->assertNull($request->getId());
        $this->assertEquals([1, 2, 3], $request->getParams());
    }

    public function testNamedParams(): void
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'subtract',
            'params' => ['subtrahend' => 23, 'minuend' => 42],
            'id' => '3',
        ];

        $request = new BaseRequest($data);

        $this->assertEquals(['subtrahend' => 23, 'minuend' => 42], $request->getParams());
    }

    public function testEmptyJsonrpcThrowsException(): void
    {
        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_REQUEST);

        new BaseRequest([
            'jsonrpc' => '',
            'method' => 'test',
        ]);
    }

    public function testMissingJsonrpcThrowsException(): void
    {
        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_REQUEST);

        new BaseRequest([
            'method' => 'test',
        ]);
    }

    public function testEmptyMethodThrowsException(): void
    {
        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_REQUEST);

        new BaseRequest([
            'jsonrpc' => '2.0',
            'method' => '',
        ]);
    }

    public function testMissingMethodThrowsException(): void
    {
        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_REQUEST);

        new BaseRequest([
            'jsonrpc' => '2.0',
        ]);
    }

    public function testParamsNotArrayThrowsException(): void
    {
        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_REQUEST);

        new BaseRequest([
            'jsonrpc' => '2.0',
            'method' => 'test',
            'params' => 'not_an_array',
        ]);
    }

    public function testIdWithIntegerValue(): void
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'id' => 42,
        ];

        $request = new BaseRequest($data);

        $this->assertEquals(42, $request->getId());
    }

    public function testIdWithNullValue(): void
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'id' => null,
        ];

        $request = new BaseRequest($data);

        $this->assertNull($request->getId());
    }

    public function testEmptyDataThrowsException(): void
    {
        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_REQUEST);

        new BaseRequest([]);
    }
}
