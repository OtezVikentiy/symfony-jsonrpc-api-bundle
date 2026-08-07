<?php

namespace OV\JsonRPCAPIBundle\Tests\Core\Services;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Services\RequestRawDataHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Built with Request::create() rather than a mock. A doubled Request has to be told what each
 * accessor returns and have its bags assigned from outside, which is a second description of an
 * object that already describes itself - and the two are free to drift apart. On Symfony 8 they
 * did: the bags became typed properties that a mock leaves uninitialised, so every one of these
 * cases died on "must not be accessed before initialization" while the code under test was fine.
 */
final class RequestRawDataHandlerTest extends TestCase
{
    private const JSON_HEADERS = ['CONTENT_TYPE' => 'application/json'];

    private RequestRawDataHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new RequestRawDataHandler();
    }

    public function testGetVersionFromV1(): void
    {
        $this->assertEquals(1, $this->handler->getVersion(Request::create('/api/v1')));
    }

    public function testGetVersionFromV2(): void
    {
        $this->assertEquals(2, $this->handler->getVersion(Request::create('/api/v2')));
    }

    public function testGetVersionFromV123(): void
    {
        $this->assertEquals(123, $this->handler->getVersion(Request::create('/api/v123')));
    }

    public function testGetVersionWithNestedPath(): void
    {
        $this->assertEquals(3, $this->handler->getVersion(Request::create('/some/prefix/api/v3')));
    }

    public function testPrepareDataWithGetRequest(): void
    {
        $request = Request::create('/api/v1?jsonrpc=2.0&method=test', Request::METHOD_GET);

        $data = $this->handler->prepareData($request);

        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals('test', $data['method']);
    }

    public function testPrepareDataWithPostJsonBody(): void
    {
        $data = $this->handler->prepareData($this->jsonRequest(
            Request::METHOD_POST,
            '{"jsonrpc":"2.0","method":"test","id":"1"}',
        ));

        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals('test', $data['method']);
        $this->assertEquals('1', $data['id']);
    }

    public function testPrepareDataWithPutRequest(): void
    {
        $data = $this->handler->prepareData($this->jsonRequest(
            Request::METHOD_PUT,
            '{"jsonrpc":"2.0","method":"update","id":"1"}',
        ));

        $this->assertEquals('update', $data['method']);
    }

    public function testPrepareDataWithPatchRequest(): void
    {
        $data = $this->handler->prepareData($this->jsonRequest(
            Request::METHOD_PATCH,
            '{"jsonrpc":"2.0","method":"patch_method","id":"1"}',
        ));

        $this->assertEquals('patch_method', $data['method']);
    }

    public function testPrepareDataWithDeleteRequest(): void
    {
        $data = $this->handler->prepareData($this->jsonRequest(
            Request::METHOD_DELETE,
            '{"jsonrpc":"2.0","method":"delete_item","id":"1"}',
        ));

        $this->assertEquals('delete_item', $data['method']);
    }

    public function testPrepareDataWithInvalidJsonThrowsParseError(): void
    {
        $request = $this->jsonRequest(
            Request::METHOD_POST,
            '{"jsonrpc": "2.0", "method": "foobar, "params": "bar", "baz]',
        );

        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::PARSE_ERROR);

        $this->handler->prepareData($request);
    }

    public function testPrepareDataWithUnsupportedMethodThrowsInvalidRequest(): void
    {
        $request = Request::create('/api/v1', 'OPTIONS');

        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_REQUEST);

        $this->handler->prepareData($request);
    }

    public function testPrepareDataIgnoresFormEncodedRequestData(): void
    {
        $request = $this->jsonRequest(Request::METHOD_POST, '{"jsonrpc":"2.0","method":"test"}');
        // Whatever ends up in the POST bag stays out of the payload: the body is the only source.
        $request->request->set('extra_field', 'value');

        $data = $this->handler->prepareData($request);

        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals('test', $data['method']);
        $this->assertArrayNotHasKey('extra_field', $data);
    }

    public function testPrepareDataWithEmptyPostContentReturnsEmptyArray(): void
    {
        // Form-encoded, so the parameters land in the POST bag and the body is empty. An empty
        // body short-circuits before the Content-Type check, which is why this is [] rather
        // than an Invalid Request.
        $request = Request::create('/api/v1', Request::METHOD_POST, ['jsonrpc' => '2.0', 'method' => 'test']);

        $this->assertSame([], $this->handler->prepareData($request));
    }

    private function jsonRequest(string $method, string $body): Request
    {
        return Request::create('/api/v1', $method, [], [], [], self::JSON_HEADERS, $body);
    }
}
