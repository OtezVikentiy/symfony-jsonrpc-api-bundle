<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Security;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Services\RequestRawDataHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ContentTypeEnforcementTest extends TestCase
{
    private const BODY = '{"jsonrpc":"2.0","method":"m","id":1}';

    public function testJsonContentTypeIsAccepted(): void
    {
        $data = (new RequestRawDataHandler())->prepareData($this->request('application/json'));

        $this->assertSame('m', $data['method']);
    }

    public function testJsonContentTypeWithCharsetIsAccepted(): void
    {
        $data = (new RequestRawDataHandler())->prepareData($this->request('application/json; charset=utf-8'));

        $this->assertSame('m', $data['method']);
    }

    public function testJsonContentTypeIsCaseInsensitive(): void
    {
        $data = (new RequestRawDataHandler())->prepareData($this->request('APPLICATION/JSON'));

        $this->assertSame('m', $data['method']);
    }

    public function testFormEncodedBodyIsRejected(): void
    {
        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_REQUEST);

        (new RequestRawDataHandler())->prepareData($this->request('application/x-www-form-urlencoded'));
    }

    public function testMissingContentTypeIsRejected(): void
    {
        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_REQUEST);

        (new RequestRawDataHandler())->prepareData($this->request(null));
    }

    public function testFormFieldsAreNoLongerMergedIntoPayload(): void
    {
        $request = Request::create(
            '/api/v1',
            'POST',
            ['method' => 'injectedByForm', 'id' => '999'],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            self::BODY,
        );

        $data = (new RequestRawDataHandler())->prepareData($request);

        $this->assertSame('m', $data['method']);
        $this->assertSame(1, $data['id']);
    }

    private function request(?string $contentType): Request
    {
        $server = $contentType === null ? [] : ['CONTENT_TYPE' => $contentType];

        return Request::create('/api/v1', 'POST', [], [], [], $server, self::BODY);
    }
}
