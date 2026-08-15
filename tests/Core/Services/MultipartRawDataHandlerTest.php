<?php

namespace OV\JsonRPCAPIBundle\Tests\Core\Services;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Services\RequestRawDataHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * The multipart transport adapter, exercised at its own boundary.
 *
 * Everything multipart-specific ends when prepareData() returns: what these cases assert is that it
 * returns the ordinary envelope array, with UploadedFile instances already sitting in params, and
 * that every shape it cannot express that way is refused rather than half-accepted.
 */
final class MultipartRawDataHandlerTest extends TestCase
{
    private const MULTIPART_HEADERS = ['CONTENT_TYPE' => 'multipart/form-data; boundary=--------boundary'];

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $tempFile) {
            if (is_file($tempFile)) {
                unlink($tempFile);
            }
        }

        $this->tempFiles = [];
    }

    public function testMultipartRequestIsDetectedFromContentType(): void
    {
        $handler = $this->handler();

        $this->assertTrue($handler->isMultipartRequest($this->multipartRequest([])));
        $this->assertFalse($handler->isMultipartRequest(Request::create('/api/v1', Request::METHOD_POST)));
    }

    public function testDisabledMultipartLeavesTheRequestRefusedAsBefore(): void
    {
        // The default. PHP has already consumed the body into $_POST/$_FILES, so getContent() is
        // empty and the request short-circuits exactly where a bodyless POST always has.
        $handler = new RequestRawDataHandler();

        $this->assertSame([], $handler->prepareData($this->multipartRequest([
            'jsonrpc' => $this->envelope(),
        ])));
    }

    public function testEnvelopeFieldIsDecodedIntoTheOrdinaryRequestArray(): void
    {
        $data = $this->handler()->prepareData($this->multipartRequest([
            'jsonrpc' => $this->envelope(['title' => 'Report']),
        ]));

        $this->assertSame('2.0', $data['jsonrpc']);
        $this->assertSame('uploadFile', $data['method']);
        $this->assertSame('Report', $data['params']['title']);
        $this->assertSame('1', $data['id']);
    }

    public function testUploadedFilesLandInParamsUnderTheirPartName(): void
    {
        $file = $this->uploadedFile('report.pdf', 'application/pdf');

        $data = $this->handler()->prepareData($this->multipartRequest(
            ['jsonrpc' => $this->envelope(['title' => 'Report'])],
            ['file' => $file],
        ));

        $this->assertSame($file, $data['params']['file']);
        $this->assertSame('Report', $data['params']['title']);
    }

    public function testFilesArriveEvenWhenTheEnvelopeCarriesNoParams(): void
    {
        $file = $this->uploadedFile();

        $data = $this->handler()->prepareData($this->multipartRequest(
            ['jsonrpc' => json_encode(['jsonrpc' => '2.0', 'method' => 'uploadFile', 'id' => '1'])],
            ['file' => $file],
        ));

        $this->assertSame($file, $data['params']['file']);
    }

    public function testMissingEnvelopeFieldIsInvalidRequest(): void
    {
        $this->expectInvalidRequest('must carry the JSON-RPC request object');

        $this->handler()->prepareData($this->multipartRequest([], ['file' => $this->uploadedFile()]));
    }

    public function testNonStringEnvelopeFieldIsInvalidRequest(): void
    {
        // `jsonrpc[]=...` produces an array in the POST bag rather than the serialized envelope.
        $this->expectInvalidRequest('must carry the JSON-RPC request object');

        $this->handler()->prepareData($this->multipartRequest(['jsonrpc' => ['2.0']]));
    }

    public function testMalformedEnvelopeIsParseError(): void
    {
        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::PARSE_ERROR);

        $this->handler()->prepareData($this->multipartRequest(['jsonrpc' => '{"jsonrpc":"2.0",']));
    }

    public function testScalarEnvelopeIsInvalidRequest(): void
    {
        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_REQUEST);

        $this->handler()->prepareData($this->multipartRequest(['jsonrpc' => '42']));
    }

    public function testBatchInsideTheEnvelopeIsRefused(): void
    {
        $this->expectInvalidRequest('Batch requests are not supported');

        $this->handler()->prepareData($this->multipartRequest([
            'jsonrpc' => json_encode([['jsonrpc' => '2.0', 'method' => 'uploadFile', 'id' => '1']]),
        ]));
    }

    public function testMultipartOnANonPostMethodIsRefused(): void
    {
        $this->expectInvalidRequest('supported for POST requests only');

        $request = Request::create('/api/v1', Request::METHOD_PUT, [], [], [], self::MULTIPART_HEADERS, '');
        $this->handler()->prepareData($request);
    }

    public function testAnEmptyEnvelopeObjectFallsThroughToTheOrdinaryEmptyRequest(): void
    {
        // `{}` decodes to the same empty array a list would, so the batch check has to let it past
        // and leave the caller with the Invalid Request an empty payload has always produced.
        $this->assertSame([], $this->handler()->prepareData($this->multipartRequest(['jsonrpc' => '{}'])));
    }

    public function testEnvelopeExceedingMaxPayloadBytesIsRefused(): void
    {
        $this->expectInvalidRequest('Payload size exceeds limit');

        $handler = new RequestRawDataHandler(maxPayloadBytes: 1024, multipartEnabled: true);
        $handler->prepareData($this->multipartRequest([
            'jsonrpc' => $this->envelope(['title' => str_repeat('a', 2048)]),
        ]));
    }

    public function testPartNameCollidingWithAParameterIsRefused(): void
    {
        $this->expectInvalidRequest('collides with a parameter of the same name');

        $this->handler()->prepareData($this->multipartRequest(
            ['jsonrpc' => $this->envelope(['file' => 'already here'])],
            ['file' => $this->uploadedFile()],
        ));
    }

    public function testFilesAlongsideByPositionParamsAreRefused(): void
    {
        $this->expectInvalidRequest('require by-name params');

        $this->handler()->prepareData($this->multipartRequest(
            ['jsonrpc' => json_encode(['jsonrpc' => '2.0', 'method' => 'uploadFile', 'params' => [1, 2], 'id' => '1'])],
            ['file' => $this->uploadedFile()],
        ));
    }

    public function testNonArrayParamsWithFilesIsInvalidRequest(): void
    {
        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_REQUEST);

        $this->handler()->prepareData($this->multipartRequest(
            ['jsonrpc' => json_encode(['jsonrpc' => '2.0', 'method' => 'uploadFile', 'params' => 'nope', 'id' => '1'])],
            ['file' => $this->uploadedFile()],
        ));
    }

    public function testNestedFilePartIsRefused(): void
    {
        $this->expectInvalidRequest('must be a single file at the top level');

        $this->handler()->prepareData($this->multipartRequest(
            ['jsonrpc' => $this->envelope()],
            ['photos' => [$this->uploadedFile()]],
        ));
    }

    public function testMaxFilesIsEnforced(): void
    {
        $this->expectInvalidRequest('File count 2 exceeds limit 1');

        $handler = new RequestRawDataHandler(multipartEnabled: true, multipartMaxFiles: 1);
        $handler->prepareData($this->multipartRequest(
            ['jsonrpc' => $this->envelope()],
            ['file' => $this->uploadedFile(), 'other' => $this->uploadedFile()],
        ));
    }

    public function testFileSizeIsNotTheTransportsBusiness(): void
    {
        // The upload is complete and on disk before this code runs, so refusing it here would save
        // nothing upload_max_filesize did not already save. multipart.max_file_bytes is applied as
        // an Assert\File constraint on the parameter instead, where the violation can name the field
        // and report the size - see MethodSpecTest and MultipartUploadTest.
        $file = $this->uploadedFile();

        $data = (new RequestRawDataHandler(multipartEnabled: true))->prepareData($this->multipartRequest(
            ['jsonrpc' => $this->envelope()],
            ['file' => $file],
        ));

        $this->assertSame($file, $data['params']['file']);
    }

    public function testJsonRequestsAreUnaffectedWhenMultipartIsEnabled(): void
    {
        $request = Request::create(
            '/api/v1',
            Request::METHOD_POST,
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"jsonrpc":"2.0","method":"test","id":"1"}',
        );

        $this->assertSame('test', $this->handler()->prepareData($request)['method']);
    }

    public function testUnrelatedContentTypeIsStillRefusedWhenMultipartIsEnabled(): void
    {
        $this->expectInvalidRequest('Content-Type must be application/json');

        $request = Request::create(
            '/api/v1',
            Request::METHOD_POST,
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            '{"jsonrpc":"2.0","method":"test","id":"1"}',
        );

        $this->handler()->prepareData($request);
    }

    private function handler(): RequestRawDataHandler
    {
        return new RequestRawDataHandler(multipartEnabled: true);
    }

    private function expectInvalidRequest(string $infoFragment): void
    {
        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_REQUEST);
        $this->expectExceptionMessageMatches(sprintf('/%s/', preg_quote($infoFragment, '/')));
    }

    private function envelope(array $params = []): string
    {
        return (string) json_encode([
            'jsonrpc' => '2.0',
            'method' => 'uploadFile',
            'params' => (object) $params,
            'id' => '1',
        ]);
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $files
     */
    private function multipartRequest(array $fields, array $files = []): Request
    {
        // Body deliberately empty: PHP parses a multipart body into $_POST/$_FILES and leaves
        // php://input empty, and a Request that pretended otherwise would test a request no server
        // ever produces.
        return Request::create('/api/v1', Request::METHOD_POST, $fields, [], $files, self::MULTIPART_HEADERS, '');
    }

    private function uploadedFile(string $originalName = 'report.pdf', string $mimeType = 'application/pdf'): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'ov_multipart_');
        file_put_contents($path, 'PDF-BYTES');
        $this->tempFiles[] = $path;

        return new UploadedFile($path, $originalName, $mimeType, null, true);
    }
}
