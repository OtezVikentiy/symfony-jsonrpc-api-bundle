<?php

namespace OV\JsonRPCAPIBundle\Tests\Core\Services;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Services\RequestRawDataHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;

final class RequestRawDataHandlerTest extends TestCase
{
    private RequestRawDataHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new RequestRawDataHandler();
    }

    public function testGetVersionFromV1(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getPathInfo')->willReturn('/api/v1');

        $this->assertEquals(1, $this->handler->getVersion($request));
    }

    public function testGetVersionFromV2(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getPathInfo')->willReturn('/api/v2');

        $this->assertEquals(2, $this->handler->getVersion($request));
    }

    public function testGetVersionFromV123(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getPathInfo')->willReturn('/api/v123');

        $this->assertEquals(123, $this->handler->getVersion($request));
    }

    public function testGetVersionWithNestedPath(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getPathInfo')->willReturn('/some/prefix/api/v3');

        $this->assertEquals(3, $this->handler->getVersion($request));
    }

    public function testPrepareDataWithGetRequest(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn(Request::METHOD_GET);
        $request->query = new InputBag([
            'jsonrpc' => '2.0',
            'method' => 'test',
        ]);

        $data = $this->handler->prepareData($request);

        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals('test', $data['method']);
    }

    public function testPrepareDataWithPostJsonBody(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn(Request::METHOD_POST);
        $request->request = new InputBag([]);
        $request->method('getContent')->willReturn('{"jsonrpc":"2.0","method":"test","id":"1"}');

        $data = $this->handler->prepareData($request);

        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals('test', $data['method']);
        $this->assertEquals('1', $data['id']);
    }

    public function testPrepareDataWithPutRequest(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn(Request::METHOD_PUT);
        $request->request = new InputBag([]);
        $request->method('getContent')->willReturn('{"jsonrpc":"2.0","method":"update","id":"1"}');

        $data = $this->handler->prepareData($request);

        $this->assertEquals('update', $data['method']);
    }

    public function testPrepareDataWithPatchRequest(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn(Request::METHOD_PATCH);
        $request->request = new InputBag([]);
        $request->method('getContent')->willReturn('{"jsonrpc":"2.0","method":"patch_method","id":"1"}');

        $data = $this->handler->prepareData($request);

        $this->assertEquals('patch_method', $data['method']);
    }

    public function testPrepareDataWithDeleteRequest(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn(Request::METHOD_DELETE);
        $request->request = new InputBag([]);
        $request->method('getContent')->willReturn('{"jsonrpc":"2.0","method":"delete_item","id":"1"}');

        $data = $this->handler->prepareData($request);

        $this->assertEquals('delete_item', $data['method']);
    }

    public function testPrepareDataWithInvalidJsonThrowsParseError(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn(Request::METHOD_POST);
        $request->request = new InputBag([]);
        $request->method('getContent')->willReturn('{"jsonrpc": "2.0", "method": "foobar, "params": "bar", "baz]');

        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::PARSE_ERROR);

        $this->handler->prepareData($request);
    }

    public function testPrepareDataWithUnsupportedMethodThrowsInvalidRequest(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn('OPTIONS');

        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_REQUEST);

        $this->handler->prepareData($request);
    }

    public function testPrepareDataMergesRequestAndJsonData(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn(Request::METHOD_POST);
        $request->request = new InputBag(['extra_field' => 'value']);
        $request->method('getContent')->willReturn('{"jsonrpc":"2.0","method":"test"}');

        $data = $this->handler->prepareData($request);

        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals('test', $data['method']);
        $this->assertEquals('value', $data['extra_field']);
    }

    public function testPrepareDataWithEmptyPostContent(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getMethod')->willReturn(Request::METHOD_POST);
        $request->request = new InputBag(['jsonrpc' => '2.0', 'method' => 'test']);
        $request->method('getContent')->willReturn('');

        $data = $this->handler->prepareData($request);

        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals('test', $data['method']);
    }
}
