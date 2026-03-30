<?php

namespace OV\JsonRPCAPIBundle\Tests\Swagger;

use OV\JsonRPCAPIBundle\Swagger\Path;
use OV\JsonRPCAPIBundle\Swagger\RequestBody;
use OV\JsonRPCAPIBundle\Swagger\Response;
use PHPUnit\Framework\TestCase;

final class PathTest extends TestCase
{
    public function testGetName(): void
    {
        $path = new Path(name: '/get_products');
        $this->assertEquals('/get_products', $path->getName());
    }

    public function testToArrayBasicStructure(): void
    {
        $requestBody = new RequestBody(contentRef: 'TestMainRequest');
        $response = new Response(code: '200', contentRef: 'TestResponse');

        $path = new Path(
            name: '/test_method',
            methodType: 'POST',
            summary: 'Test summary',
            description: 'Test description',
            requestBody: $requestBody,
            responses: [$response],
        );

        $result = $path->toArray();

        $this->assertArrayHasKey('post', $result);
        $postData = $result['post'];

        $this->assertEquals('Test summary', $postData['summary']);
        $this->assertEquals('Test description', $postData['description']);
        $this->assertArrayHasKey('requestBody', $postData);
        $this->assertArrayHasKey('responses', $postData);
        $this->assertArrayHasKey('200', $postData['responses']);
        $this->assertArrayNotHasKey('parameters', $postData);
    }

    public function testToArrayWithTags(): void
    {
        $requestBody = new RequestBody(contentRef: 'TestRequest');
        $path = new Path(
            name: '/test',
            methodType: 'POST',
            requestBody: $requestBody,
            tags: ['math', 'utils'],
        );

        $result = $path->toArray();
        $this->assertEquals(['math', 'utils'], $result['post']['tags']);
    }

    public function testToArrayWithoutTags(): void
    {
        $requestBody = new RequestBody(contentRef: 'TestRequest');
        $path = new Path(
            name: '/test',
            methodType: 'POST',
            requestBody: $requestBody,
            tags: [],
        );

        $result = $path->toArray();
        $this->assertArrayNotHasKey('tags', $result['post']);
    }

    public function testMethodTypeLowercased(): void
    {
        $requestBody = new RequestBody(contentRef: 'TestRequest');
        $path = new Path(name: '/test', methodType: 'PUT', requestBody: $requestBody);

        $result = $path->toArray();
        $this->assertArrayHasKey('put', $result);
    }

    public function testGetMethodType(): void
    {
        $requestBody = new RequestBody(contentRef: 'TestRequest');
        $path = new Path(name: '/test', methodType: 'GET', requestBody: $requestBody);

        $result = $path->toArray();
        $this->assertArrayHasKey('get', $result);
    }

    public function testMultipleResponses(): void
    {
        $requestBody = new RequestBody(contentRef: 'TestRequest');
        $path = new Path(
            name: '/test',
            methodType: 'POST',
            requestBody: $requestBody,
            responses: [
                new Response(code: '200', contentRef: 'SuccessResponse'),
                new Response(code: '400', contentRef: 'ErrorResponse'),
            ],
        );

        $result = $path->toArray();
        $this->assertArrayHasKey('200', $result['post']['responses']);
        $this->assertArrayHasKey('400', $result['post']['responses']);
    }
}
