<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Security;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Services\RequestRawDataHandler;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testJsonContentTypePaddedWithTabsIsStillAccepted(): void
    {
        $data = (new RequestRawDataHandler())->prepareData($this->request("\tapplication/json\t"));

        $this->assertSame('m', $data['method']);
    }

    /**
     * Spaces and tabs are what RFC 7230 allows around a field value, and they are all that is
     * stripped. A trailing CR or LF therefore makes the header malformed rather than merely padded -
     * a stricter reading than trim()'s default, and a deliberate one: line terminators cannot reach
     * a Content-Type through any conforming server, since obs-fold is deprecated in HTTP/1.1 and
     * absent from HTTP/2, so anything carrying them was assembled by hand.
     *
     * @param string $contentType a media type with a byte that is not RFC 7230 optional whitespace
     */
    #[DataProvider('mediaTypesWithNonWhitespacePadding')]
    public function testMediaTypePaddedWithSomethingOtherThanSpaceOrTabIsRejected(string $contentType): void
    {
        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_REQUEST);

        (new RequestRawDataHandler())->prepareData($this->request($contentType));
    }

    public static function mediaTypesWithNonWhitespacePadding(): array
    {
        return [
            'NUL byte' => ["application/json\0"],
            'carriage return and line feed' => ["application/json\r\n"],
            'carriage return' => ["application/json\r"],
            'line feed' => ["application/json\n"],
            'vertical tab' => ["application/json\x0B"],
        ];
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
