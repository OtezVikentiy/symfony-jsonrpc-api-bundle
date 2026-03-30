<?php

namespace OV\JsonRPCAPIBundle\Tests\Core\Response;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Response\ErrorResponse;
use OV\JsonRPCAPIBundle\Core\Response\BaseJsonResponseInterface;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;
use PHPUnit\Framework\TestCase;

final class ErrorResponseTest extends TestCase
{
    public function testWithJRPCException(): void
    {
        $exception = new JRPCException('Parse error.', JRPCException::PARSE_ERROR);
        $response = new ErrorResponse(error: $exception, id: '1');

        $error = $response->getError();

        $this->assertEquals(-32700, $error['code']);
        $this->assertEquals('Parse error.', $error['message']);
    }

    public function testWithThrowable(): void
    {
        $exception = new \RuntimeException('Something went wrong', 500);
        $response = new ErrorResponse(error: $exception, id: '2');

        $error = $response->getError();

        $this->assertEquals(500, $error['code']);
        $this->assertEquals('Something went wrong', $error['message']);
    }

    public function testGetJsonrpc(): void
    {
        $exception = new JRPCException('Error', JRPCException::INTERNAL_ERROR);
        $response = new ErrorResponse(error: $exception);

        $this->assertEquals('2.0', $response->getJsonrpc());
    }

    public function testGetId(): void
    {
        $exception = new JRPCException('Error', JRPCException::INTERNAL_ERROR);
        $response = new ErrorResponse(error: $exception, id: '42');

        $this->assertEquals('42', $response->getId());
    }

    public function testImplementsInterfaces(): void
    {
        $exception = new JRPCException('Error', JRPCException::INTERNAL_ERROR);
        $response = new ErrorResponse(error: $exception);

        $this->assertInstanceOf(OvResponseInterface::class, $response);
        $this->assertInstanceOf(BaseJsonResponseInterface::class, $response);
    }

    public function testWithAdditionalInfo(): void
    {
        $exception = new JRPCException('Invalid params.', JRPCException::INVALID_PARAMS, 'Field X is required');
        $response = new ErrorResponse(error: $exception, id: '1');

        $error = $response->getError();

        $this->assertEquals(-32602, $error['code']);
        $this->assertStringContainsString('Invalid params.', $error['message']);
        $this->assertStringContainsString('Field X is required', $error['message']);
    }

    public function testErrorResponseStructure(): void
    {
        $exception = new JRPCException('Method not found.', JRPCException::METHOD_NOT_FOUND);
        $response = new ErrorResponse(error: $exception, id: '5');

        $error = $response->getError();

        $this->assertArrayHasKey('code', $error);
        $this->assertArrayHasKey('message', $error);
        $this->assertCount(2, $error);
    }
}
