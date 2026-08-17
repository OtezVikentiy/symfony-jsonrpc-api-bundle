<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\RPC\V1\UploadFile\UploadFileRequest;
use OV\JsonRPCAPIBundle\RPC\V1\UploadFileMethod;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * A real multipart POST, end to end: the file part reaches the handler as an UploadedFile and the
 * answer is an ordinary JSON-RPC envelope. Nothing below the transport adapter knows the difference.
 */
final class MultipartUploadTest extends AbstractControllerTestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function after(): void
    {
        foreach ($this->tempFiles as $tempFile) {
            if (is_file($tempFile)) {
                unlink($tempFile);
            }
        }

        $this->tempFiles = [];
    }

    public function testFileReachesTheHandlerAndTheResponseIsAnOrdinaryEnvelope(): void
    {
        $this->multipartEnabled = true;
        $this->sendAsMultipart = true;
        $this->multipartFiles = ['file' => $this->uploadedFile()];

        $result = $this->executeControllerTest($this->envelope(), $this->uploadFileSpec());

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame(
            json_encode([
                'jsonrpc' => '2.0',
                'result' => ['originalName' => 'report.pdf', 'title' => 'Quarterly report', 'size' => 9],
                'id' => '1',
            ]),
            $result->getContent(),
        );
    }

    /**
     * The file-typed field needs no validation machinery written here: the compiler turns a declared
     * UploadedFile into Assert\Type followed by Assert\File, through the same path that produces
     * Assert\Type('int') for an int field. A well-formed upload has to survive both.
     */
    public function testRealValidatorAcceptsTheUploadedFile(): void
    {
        $this->multipartEnabled = true;
        $this->sendAsMultipart = true;
        $this->useRealValidator = true;
        $this->multipartFiles = ['file' => $this->uploadedFile()];

        $result = $this->executeControllerTest($this->envelope(), $this->uploadFileSpec());

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertStringContainsString('"originalName":"report.pdf"', (string) $result->getContent());
    }

    /**
     * multipart.max_file_bytes is an Assert\File constraint on the parameter, so the refusal is the
     * one Symfony writes: -32602 Invalid params, naming the field, reporting the size and the limit.
     * The transport does not look at the size at all - by the time it runs, the upload is on disk
     * and refusing it there would save nothing upload_max_filesize did not already save.
     */
    public function testAFileOverTheConfiguredLimitIsInvalidParams(): void
    {
        $this->multipartEnabled = true;
        $this->sendAsMultipart = true;
        $this->useRealValidator = true;
        $this->multipartFiles = ['file' => $this->uploadedFile()];

        $result = $this->executeControllerTest(
            $this->envelope(),
            $this->uploadFileSpec(maxFileBytes: 4),
        );

        $error = $this->decodeError($result);
        $this->assertSame(-32602, $error['code']);
        $this->assertStringContainsString('file', $error['message']);
        $this->assertStringContainsString('too large', $error['message']);
    }

    /**
     * A file that failed to upload arrives as an UploadedFile with isValid() false - the shape a
     * request over php.ini's upload_max_filesize produces. Assert\File maps every one of PHP's
     * upload error codes to a message; without it this object reached the handler, which would read
     * a file that is not there.
     */
    public function testAFailedUploadIsReportedRatherThanHandedToTheMethod(): void
    {
        $this->multipartEnabled = true;
        $this->sendAsMultipart = true;
        $this->useRealValidator = true;
        $this->multipartFiles = ['file' => $this->failedUpload()];

        $result = $this->executeControllerTest($this->envelope(), $this->uploadFileSpec());

        $error = $this->decodeError($result);
        $this->assertSame(-32602, $error['code']);
        $this->assertStringContainsString('too large', $error['message'], "PHP's UPLOAD_ERR_INI_SIZE, in Symfony's words");
    }

    public function testMethodWithoutAcceptsMultipartIsRefused(): void
    {
        $this->multipartEnabled = true;
        $this->sendAsMultipart = true;
        $this->multipartFiles = ['file' => $this->uploadedFile()];

        $result = $this->setValidateMethodExpectation('never')->executeControllerTest(
            $this->envelope(),
            $this->uploadFileSpec(acceptsMultipart: false),
        );

        $error = $this->decodeError($result);
        $this->assertSame(-32600, $error['code']);
        $this->assertStringContainsString('Method does not accept multipart/form-data.', $error['message']);
    }

    public function testMultipartDisabledGloballyRefusesTheRequest(): void
    {
        // The default. Nothing about the method changes; the transport is simply not open.
        $this->sendAsMultipart = true;
        $this->multipartFiles = ['file' => $this->uploadedFile()];

        $result = $this->setValidateMethodExpectation('never')
            ->executeControllerTest($this->envelope(), $this->uploadFileSpec());

        $this->assertSame(-32600, $this->decodeError($result)['code']);
    }

    public function testMissingEnvelopeFieldIsRefused(): void
    {
        $this->multipartEnabled = true;
        $this->sendAsMultipart = true;
        $this->omitMultipartEnvelope = true;
        $this->multipartFiles = ['file' => $this->uploadedFile()];

        $result = $this->setValidateMethodExpectation('never')
            ->executeControllerTest($this->envelope(), $this->uploadFileSpec());

        $this->assertSame(-32600, $this->decodeError($result)['code']);
    }

    /**
     * @return array{code: int, message: string}
     */
    private function decodeError(mixed $result): array
    {
        $this->assertInstanceOf(JsonResponse::class, $result);
        $decoded = json_decode((string) $result->getContent(), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('error', $decoded);

        return $decoded['error'];
    }

    private function envelope(): array
    {
        return [
            'jsonrpc' => '2.0',
            'method' => 'uploadFile',
            'params' => ['title' => 'Quarterly report'],
            'id' => '1',
        ];
    }

    private function uploadFileSpec(bool $acceptsMultipart = true, int|string|null $maxFileBytes = null): MethodSpec
    {
        return new MethodSpec(
            methodClass: UploadFileMethod::class,
            requestType: 'POST',
            methodName: 'uploadFile',
            requestMetadata: new RequestMetadata(
                request: UploadFileRequest::class,
                allParameters: [
                    ['name' => 'file', 'type' => UploadedFile::class],
                    ['name' => 'title', 'type' => 'string'],
                ],
                requiredParameters: [],
                requestGetters: ['file' => 'getFile', 'title' => 'getTitle'],
                requestSetters: ['file' => 'setFile', 'title' => 'setTitle'],
                requestAdders: [],
                validators: [
                    'file' => ['allowsNull' => false, 'type' => UploadedFile::class],
                    'title' => ['allowsNull' => false, 'type' => 'string'],
                ],
            ),
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: false),
            acceptsMultipart: $acceptsMultipart,
            maxFileBytes: $maxFileBytes,
        );
    }

    /**
     * What PHP hands over when the upload exceeded upload_max_filesize: an UploadedFile carrying an
     * error code and no readable temporary file.
     */
    private function failedUpload(): UploadedFile
    {
        return new UploadedFile(
            $this->uploadedFile()->getPathname(),
            'report.pdf',
            'application/pdf',
            UPLOAD_ERR_INI_SIZE,
            true,
        );
    }

    private function uploadedFile(): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'ov_multipart_');
        file_put_contents($path, 'PDF-BYTES');
        $this->tempFiles[] = $path;

        return new UploadedFile($path, 'report.pdf', 'application/pdf', null, true);
    }
}
