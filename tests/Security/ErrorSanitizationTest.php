<?php

namespace OV\JsonRPCAPIBundle\Tests\Security;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Services\ErrorSanitizer;
use OV\JsonRPCAPIBundle\Core\Services\HeadersPreparer;
use OV\JsonRPCAPIBundle\Core\Services\ResponseService;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;

final class ErrorSanitizationTest extends TestCase
{
    public function testInternalExceptionIsHiddenFromClient(): void
    {
        $logger = new InMemoryLogger();
        $service = new ResponseService(
            new HeadersPreparer(['*']),
            new ErrorSanitizer(exposeInternalErrors: false, logger: $logger),
        );

        $response = $service->prepareErrorResponse(
            new RuntimeException('DB connection failed: host=db user=root pass=secret'),
            id: 1,
        );

        $payload = json_decode($response->getContent(), true);

        $this->assertSame(JRPCException::INTERNAL_ERROR, $payload['error']['code']);
        $this->assertSame('Internal error.', $payload['error']['message']);
        $this->assertStringNotContainsString('secret', $response->getContent());
        $this->assertStringNotContainsString('pass=', $response->getContent());
        $this->assertSame(1, $payload['id']);

        $this->assertCount(1, $logger->records);
        $this->assertStringContainsString('Unhandled exception', $logger->records[0]['message']);
        $this->assertSame('DB connection failed: host=db user=root pass=secret', $logger->records[0]['context']['exception']->getMessage());
    }

    public function testJrpcExceptionIsPassedThrough(): void
    {
        $service = new ResponseService(
            new HeadersPreparer(['*']),
            new ErrorSanitizer(exposeInternalErrors: false),
        );

        $response = $service->prepareErrorResponse(
            new JRPCException('Invalid params.', JRPCException::INVALID_PARAMS, 'field x required'),
            id: 42,
        );

        $payload = json_decode($response->getContent(), true);
        $this->assertSame(JRPCException::INVALID_PARAMS, $payload['error']['code']);
        $this->assertStringContainsString('field x required', $payload['error']['message']);
    }

    public function testExposeInternalErrorsBypassesSanitization(): void
    {
        $service = new ResponseService(
            new HeadersPreparer(['*']),
            new ErrorSanitizer(exposeInternalErrors: true),
        );

        $response = $service->prepareErrorResponse(
            new RuntimeException('debug-only details'),
            id: 1,
        );

        $payload = json_decode($response->getContent(), true);
        $this->assertStringContainsString('debug-only details', $payload['error']['message']);
    }

}

final class InMemoryLogger extends AbstractLogger
{
    public array $records = [];

    // Untyped $message on purpose: psr/log 1.x declares log() without parameter types, and a
    // child narrowing to string|Stringable is an incompatible signature there. The manifest
    // allows ^1.1 || ^2.0 || ^3.0, so the double has to satisfy the loosest of the three.
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
