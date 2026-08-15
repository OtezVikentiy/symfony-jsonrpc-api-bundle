<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Spec;

use OV\JsonRPCAPIBundle\Core\Logging\NullJsonRpcCallLogger;
use OV\JsonRPCAPIBundle\Core\Services\ErrorSanitizer;
use OV\JsonRPCAPIBundle\Core\Services\HeadersPreparer;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler;
use OV\JsonRPCAPIBundle\Core\Services\ResponseService;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use OV\JsonRPCAPIBundle\RPC\V1\UploadFile\UploadFileRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validation;

/**
 * The invariant hydration rests on once a transport can produce typed values: a value that is
 * already an instance of the declared type is handed to the setter as it is, never rebuilt.
 *
 * Both nested-DTO branches assume the value is raw JSON and construct the declared class out of it.
 * For an UploadedFile that assumption is wrong in the worst way - the class would be built out of an
 * instance of itself - and the request would come back as a type error naming a parameter the caller
 * sent correctly.
 *
 * The nested branch is reached through reflection because no request can reach it today: file parts
 * live at the top level of params, so the only value hydration ever sees pre-typed arrives one level
 * above this. The rule is written as an invariant rather than as an UploadedFile special case, which
 * is exactly why it is worth pinning where a request cannot yet go.
 */
final class MultipartPassthroughTest extends TestCase
{
    private string $tempFile = '';

    protected function tearDown(): void
    {
        if ($this->tempFile !== '' && is_file($this->tempFile)) {
            unlink($this->tempFile);
        }

        $this->tempFile = '';
    }

    public function testAValueAlreadyOfTheDeclaredTypeIsPassedToTheSetterUntouched(): void
    {
        $file = $this->uploadedFile();

        $hydrated = $this->prepareParametersFromClass(UploadFileRequest::class, [
            'file' => $file,
            'title' => 'Quarterly report',
        ]);

        $this->assertInstanceOf(UploadFileRequest::class, $hydrated);
        $this->assertSame($file, $hydrated->getFile(), 'the file must be the very instance the transport produced');
        $this->assertSame('Quarterly report', $hydrated->getTitle());
    }

    public function testASubclassOfTheDeclaredTypeIsPassedThroughToo(): void
    {
        // The rule is instanceof, not an exact class match: an application is free to hand its own
        // UploadedFile descendant to a setter that declares the base type, and rebuilding it would
        // lose whatever the descendant carries.
        $file = new DescendantUploadedFile($this->uploadedFile()->getPathname(), 'report.pdf', 'application/pdf', null, true);

        $hydrated = $this->prepareParametersFromClass(UploadFileRequest::class, ['file' => $file]);

        $this->assertInstanceOf(UploadFileRequest::class, $hydrated);
        $this->assertSame($file, $hydrated->getFile());
    }

    private function prepareParametersFromClass(string $class, array $values): object
    {
        $method = new ReflectionMethod(RequestHandler::class, 'prepareParametersFromClass');
        $method->setAccessible(true);

        return $method->invoke($this->handler(), $class, $values);
    }

    private function handler(): RequestHandler
    {
        $headersPreparer = new HeadersPreparer(['*']);

        return new RequestHandler(
            $this->createMock(Security::class),
            new MethodSpecCollection(),
            Validation::createValidator(),
            $headersPreparer,
            $this->createMock(ServiceLocator::class),
            new ResponseService($headersPreparer, new ErrorSanitizer(exposeInternalErrors: false)),
            new NullJsonRpcCallLogger(),
        );
    }

    private function uploadedFile(): UploadedFile
    {
        $this->tempFile = (string) tempnam(sys_get_temp_dir(), 'ov_multipart_');
        file_put_contents($this->tempFile, 'PDF-BYTES');

        return new UploadedFile($this->tempFile, 'report.pdf', 'application/pdf', null, true);
    }
}

final class DescendantUploadedFile extends UploadedFile
{
}
