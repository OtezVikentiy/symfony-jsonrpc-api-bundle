<?php

namespace OV\JsonRPCAPIBundle\Tests\Core\Services;

use OV\JsonRPCAPIBundle\Core\Response\BaseResponse;
use OV\JsonRPCAPIBundle\Core\Response\ErrorResponse;
use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Services\HeadersPreparer;
use OV\JsonRPCAPIBundle\Core\Services\ResponseService;
use PHPUnit\Framework\TestCase;

final class ResponseServiceTest extends TestCase
{
    private ResponseService $responseService;

    protected function setUp(): void
    {
        $headersPreparer = new HeadersPreparer(['*']);
        $this->responseService = new ResponseService($headersPreparer);
    }

    public function testPrepareJsonResponseWithBaseResponse(): void
    {
        $baseResponse = new BaseResponse(result: ['key' => 'value'], id: '1');
        $response = $this->responseService->prepareJsonResponse($baseResponse);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('2.0', $content['jsonrpc']);
        $this->assertEquals(['key' => 'value'], $content['result']);
        $this->assertEquals('1', $content['id']);
    }

    public function testPrepareJsonResponseWithErrorResponse(): void
    {
        $exception = new JRPCException('Parse error.', JRPCException::PARSE_ERROR);
        $errorResponse = new ErrorResponse(error: $exception, id: '1');
        $response = $this->responseService->prepareJsonResponse($errorResponse);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('2.0', $content['jsonrpc']);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals(-32700, $content['error']['code']);
        $this->assertEquals('Parse error.', $content['error']['message']);
    }

    public function testResponseContainsCorrectHeaders(): void
    {
        $baseResponse = new BaseResponse(result: null, id: '1');
        $response = $this->responseService->prepareJsonResponse($baseResponse);

        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testResponseWithMultipleOrigins(): void
    {
        $headersPreparer = new HeadersPreparer(['https://a.com', 'https://b.com']);
        $responseService = new ResponseService($headersPreparer);

        $baseResponse = new BaseResponse(result: null, id: '1');
        $response = $responseService->prepareJsonResponse($baseResponse);

        $this->assertEquals('https://a.com, https://b.com', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testResponseStatusIs200(): void
    {
        $baseResponse = new BaseResponse(result: 'test', id: '1');
        $response = $this->responseService->prepareJsonResponse($baseResponse);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testResponseWithScalarResult(): void
    {
        $baseResponse = new BaseResponse(result: 42, id: '1');
        $response = $this->responseService->prepareJsonResponse($baseResponse);

        $content = json_decode($response->getContent(), true);
        $this->assertEquals(42, $content['result']);
    }

    public function testResponseWithNullId(): void
    {
        $baseResponse = new BaseResponse(result: 'test');
        $response = $this->responseService->prepareJsonResponse($baseResponse);

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('2.0', $content['jsonrpc']);
        $this->assertEquals('test', $content['result']);
    }
}
