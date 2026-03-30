<?php

namespace OV\JsonRPCAPIBundle\Tests\Core\Response;

use OV\JsonRPCAPIBundle\Core\Response\BaseResponse;
use OV\JsonRPCAPIBundle\Core\Response\BaseJsonResponseInterface;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;
use PHPUnit\Framework\TestCase;

final class BaseResponseTest extends TestCase
{
    public function testConstructorWithResultAndId(): void
    {
        $response = new BaseResponse(result: ['key' => 'value'], id: '1');

        $this->assertEquals(['key' => 'value'], $response->getResult());
        $this->assertEquals('1', $response->getId());
    }

    public function testGetJsonrpcReturnsDefault(): void
    {
        $response = new BaseResponse(result: null);

        $this->assertEquals('2.0', $response->getJsonrpc());
    }

    public function testGetIdDefaultsToNull(): void
    {
        $response = new BaseResponse(result: 'test');

        $this->assertNull($response->getId());
    }

    public function testGetResultWithScalar(): void
    {
        $response = new BaseResponse(result: 42);

        $this->assertEquals(42, $response->getResult());
    }

    public function testGetResultWithObject(): void
    {
        $obj = new \stdClass();
        $obj->name = 'test';
        $response = new BaseResponse(result: $obj);

        $this->assertSame($obj, $response->getResult());
    }

    public function testGetResultWithArray(): void
    {
        $result = ['hello', 5];
        $response = new BaseResponse(result: $result, id: '9');

        $this->assertEquals($result, $response->getResult());
    }

    public function testImplementsInterfaces(): void
    {
        $response = new BaseResponse(result: null);

        $this->assertInstanceOf(OvResponseInterface::class, $response);
        $this->assertInstanceOf(BaseJsonResponseInterface::class, $response);
    }

    public function testCustomJsonrpcVersion(): void
    {
        $response = new BaseResponse(result: null, id: null, jsonrpc: '1.0');

        $this->assertEquals('1.0', $response->getJsonrpc());
    }

    public function testIdWithIntegerValue(): void
    {
        $response = new BaseResponse(result: 'test', id: 42);

        $this->assertEquals(42, $response->getId());
        $this->assertIsInt($response->getId());
    }

    public function testIdWithStringValue(): void
    {
        $response = new BaseResponse(result: 'test', id: 'abc-123');

        $this->assertEquals('abc-123', $response->getId());
        $this->assertIsString($response->getId());
    }

    public function testIdAcceptsMixedTypes(): void
    {
        $responseNull = new BaseResponse(result: null, id: null);
        $this->assertNull($responseNull->getId());

        $responseStr = new BaseResponse(result: null, id: '1');
        $this->assertSame('1', $responseStr->getId());

        $responseInt = new BaseResponse(result: null, id: 99);
        $this->assertSame(99, $responseInt->getId());
    }
}
