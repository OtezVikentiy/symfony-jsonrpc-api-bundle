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

    public function testInvalidCodeThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Undefined code');

        new JRPCException('Bad code.', 999);
    }

    public function testInvalidNegativeCodeThrowsException(): void
    {
        $this->expectException(Exception::class);

        new JRPCException('Bad code.', -99999);
    }

    public function testInvalidCodeZeroThrowsException(): void
    {
        $this->expectException(Exception::class);

        new JRPCException('Bad code.', 0);
    }

    public function testServerErrorRangeValidCodes(): void
    {
        // Codes in range -32000 to -32099 are valid server error codes
        $e1 = new JRPCException('Server error.', -32000);
        $this->assertEquals(-32000, $e1->getCode());

        $e2 = new JRPCException('Server error.', -32050);
        $this->assertEquals(-32050, $e2->getCode());

        $e3 = new JRPCException('Server error.', -32099);
        $this->assertEquals(-32099, $e3->getCode());
    }

    public function testCodeOutsideServerErrorRangeThrows(): void
    {
        $this->expectException(Exception::class);

        // -32100 is outside valid range
        new JRPCException('Bad.', -32100);
    }

    public function testCodeAboveServerErrorRangeThrows(): void
    {
        $this->expectException(Exception::class);

        // 1 is outside all valid ranges
        new JRPCException('Bad.', 1);
    }
}
