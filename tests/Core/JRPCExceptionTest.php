<?php

namespace OV\JsonRPCAPIBundle\Tests\Core;

use Exception;
use OV\JsonRPCAPIBundle\Core\JRPCException;
use PHPUnit\Framework\TestCase;

final class JRPCExceptionTest extends TestCase
{
    public function testParseError(): void
    {
        $e = new JRPCException('Parse error.', JRPCException::PARSE_ERROR);
        $this->assertEquals(-32700, $e->getCode());
        $this->assertEquals('Parse error.', $e->getMessage());
    }

    public function testInvalidRequest(): void
    {
        $e = new JRPCException('Invalid Request.', JRPCException::INVALID_REQUEST);
        $this->assertEquals(-32600, $e->getCode());
        $this->assertEquals('Invalid Request.', $e->getMessage());
    }

    public function testMethodNotFound(): void
    {
        $e = new JRPCException('Method not found.', JRPCException::METHOD_NOT_FOUND);
        $this->assertEquals(-32601, $e->getCode());
        $this->assertEquals('Method not found.', $e->getMessage());
    }

    public function testInvalidParams(): void
    {
        $e = new JRPCException('Invalid params.', JRPCException::INVALID_PARAMS);
        $this->assertEquals(-32602, $e->getCode());
        $this->assertEquals('Invalid params.', $e->getMessage());
    }

    public function testInternalError(): void
    {
        $e = new JRPCException('Internal error.', JRPCException::INTERNAL_ERROR);
        $this->assertEquals(-32603, $e->getCode());
        $this->assertEquals('Internal error.', $e->getMessage());
    }

    public function testServerError(): void
    {
        $e = new JRPCException('Server error.', JRPCException::SERVER_ERROR);
        $this->assertEquals(-32000, $e->getCode());
        $this->assertEquals('Server error.', $e->getMessage());
    }

    public function testWithAdditionalInfo(): void
    {
        $e = new JRPCException('Invalid params.', JRPCException::INVALID_PARAMS, 'Field name is required');
        $this->assertEquals(-32602, $e->getCode());
        $this->assertEquals('Invalid params. Additional info: Field name is required', $e->getMessage());
    }

    public function testWithoutAdditionalInfo(): void
    {
        $e = new JRPCException('Parse error.', JRPCException::PARSE_ERROR);
        $this->assertStringNotContainsString('Additional info', $e->getMessage());
    }

    public function testWithPreviousException(): void
    {
        $previous = new Exception('Previous error');
        $e = new JRPCException('Server error.', JRPCException::SERVER_ERROR, '', $previous);
        $this->assertSame($previous, $e->getPrevious());
    }

    public function testErrorConstants(): void
    {
        $this->assertEquals(-32700, JRPCException::PARSE_ERROR);
        $this->assertEquals(-32600, JRPCException::INVALID_REQUEST);
        $this->assertEquals(-32601, JRPCException::METHOD_NOT_FOUND);
        $this->assertEquals(-32602, JRPCException::INVALID_PARAMS);
        $this->assertEquals(-32603, JRPCException::INTERNAL_ERROR);
        $this->assertEquals(-32000, JRPCException::SERVER_ERROR);
    }
}
