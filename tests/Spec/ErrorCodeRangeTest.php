<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Spec;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Response\ErrorResponse;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ErrorCodeRangeTest extends TestCase
{
    public function testArbitraryThrowableCodeIsReplacedWithInternalError(): void
    {
        $response = new ErrorResponse(error: new RuntimeException('boom'), id: 1);

        $this->assertSame(JRPCException::INTERNAL_ERROR, $response->toArray()['error']['code']);
    }

    public function testStandardCodesPassThroughUnchanged(): void
    {
        $standardCodes = [
            JRPCException::PARSE_ERROR,
            JRPCException::INVALID_REQUEST,
            JRPCException::METHOD_NOT_FOUND,
            JRPCException::INVALID_PARAMS,
            JRPCException::INTERNAL_ERROR,
        ];

        foreach ($standardCodes as $code) {
            $response = new ErrorResponse(error: new JRPCException('Error.', $code), id: 1);

            $this->assertSame($code, $response->toArray()['error']['code']);
        }
    }

    public function testServerErrorRangeUpperBoundPassesThroughUnchanged(): void
    {
        $response = new ErrorResponse(error: new JRPCException('Server error.', -32000), id: 1);

        $this->assertSame(-32000, $response->toArray()['error']['code']);
    }

    public function testServerErrorRangeLowerBoundPassesThroughUnchanged(): void
    {
        $response = new ErrorResponse(error: new JRPCException('Server error.', -32099), id: 1);

        $this->assertSame(-32099, $response->toArray()['error']['code']);
    }

    public function testCodeJustAboveServerErrorRangeIsReplaced(): void
    {
        $response = new ErrorResponse(error: new RuntimeException('boom', -31999), id: 1);

        $this->assertSame(JRPCException::INTERNAL_ERROR, $response->toArray()['error']['code']);
    }

    public function testCodeJustBelowServerErrorRangeIsReplaced(): void
    {
        $response = new ErrorResponse(error: new RuntimeException('boom', -32100), id: 1);

        $this->assertSame(JRPCException::INTERNAL_ERROR, $response->toArray()['error']['code']);
    }
}
