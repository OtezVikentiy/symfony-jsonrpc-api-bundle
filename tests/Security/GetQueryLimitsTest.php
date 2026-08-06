<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Security;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Services\RequestRawDataHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class GetQueryLimitsTest extends TestCase
{
    public function testOversizedQueryStringIsRejected(): void
    {
        $handler = new RequestRawDataHandler(maxPayloadBytes: 1024);
        $request = Request::create('/api/v1?jsonrpc=2.0&method=m&id=1&pad=' . str_repeat('A', 5000), 'GET');

        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_REQUEST);

        $handler->prepareData($request);
    }

    public function testQueryStringAtLimitIsAccepted(): void
    {
        $handler = new RequestRawDataHandler(maxPayloadBytes: 1024);
        $request = Request::create('/api/v1?jsonrpc=2.0&method=m&id=1', 'GET');

        $data = $handler->prepareData($request);

        $this->assertSame('m', $data['method']);
    }

    public function testDeeplyNestedQueryIsRejected(): void
    {
        $handler = new RequestRawDataHandler(maxJsonDepth: 3);
        $request = Request::create('/api/v1?jsonrpc=2.0&method=m&id=1&a[b][c][d][e]=1', 'GET');

        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_REQUEST);

        $handler->prepareData($request);
    }

    public function testDuplicateKeyQueryPollutionIsRejected(): void
    {
        $handler = new RequestRawDataHandler(maxPayloadBytes: 1024);
        $oversizedValue = str_repeat('A', 5000);
        $request = Request::create(
            '/api/v1?jsonrpc=2.0&method=m&id=1&pad=' . $oversizedValue . '&pad=x',
            'GET',
        );

        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_REQUEST);

        $handler->prepareData($request);
    }

    public function testQueryWithPlusSignsIsNotFalselyRejected(): void
    {
        $handler = new RequestRawDataHandler(maxPayloadBytes: 200);
        $request = Request::create(
            '/api/v1?jsonrpc=2.0&method=m&id=1&pad=' . str_repeat('+', 100),
            'GET',
        );

        $data = $handler->prepareData($request);

        $this->assertSame('m', $data['method']);
    }
}
