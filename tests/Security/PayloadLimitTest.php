<?php

namespace OV\JsonRPCAPIBundle\Tests\Security;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Services\RequestRawDataHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PayloadLimitTest extends TestCase
{
    public function testOversizedPayloadIsRejected(): void
    {
        $handler = new RequestRawDataHandler(maxPayloadBytes: 1024, maxJsonDepth: 64);
        $request = $this->buildRequest(str_repeat('a', 2048));

        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_REQUEST);

        $handler->prepareData($request);
    }

    public function testDeeplyNestedJsonIsRejected(): void
    {
        $handler = new RequestRawDataHandler(maxPayloadBytes: 1048576, maxJsonDepth: 8);

        $payload = $this->buildNestedJson(20);
        $request = $this->buildRequest($payload);

        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::PARSE_ERROR);

        $handler->prepareData($request);
    }

    public function testPayloadAtLimitIsAccepted(): void
    {
        $body = '{"jsonrpc":"2.0","method":"x","id":1}';
        $handler = new RequestRawDataHandler(maxPayloadBytes: strlen($body), maxJsonDepth: 64);
        $request = $this->buildRequest($body);

        $data = $handler->prepareData($request);

        $this->assertSame('2.0', $data['jsonrpc']);
        $this->assertSame('x', $data['method']);
    }

    public function testInvalidJsonThrowsParseError(): void
    {
        $handler = new RequestRawDataHandler();
        $request = $this->buildRequest('{not-json');

        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::PARSE_ERROR);

        $handler->prepareData($request);
    }

    private function buildRequest(string $content): Request
    {
        // A real Request, not a mock: on Symfony 8 the bags are typed properties that a mock
        // leaves uninitialised, so a doubled Request fails before reaching the limit under test.
        return Request::create(
            '/api/v1',
            Request::METHOD_POST,
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $content,
        );
    }

    private function buildNestedJson(int $depth): string
    {
        $open = str_repeat('{"a":', $depth);
        $close = str_repeat('}', $depth);

        return $open . '1' . $close;
    }
}
