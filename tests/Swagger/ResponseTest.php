<?php

namespace OV\JsonRPCAPIBundle\Tests\Swagger;

use OV\JsonRPCAPIBundle\Swagger\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testToArray(): void
    {
        $response = new Response(code: '200', contentRef: 'TestResponse', description: 'Success');
        $result = $response->toArray();

        $this->assertArrayHasKey('description', $result);
        $this->assertEquals('Success', $result['description']);
        $this->assertArrayHasKey('content', $result);
        $this->assertEquals(
            '#/components/schemas/TestResponse',
            $result['content']['application/json']['schema']['$ref']
        );
    }

    public function testGetCode(): void
    {
        $response = new Response(code: '200');
        $this->assertEquals('200', $response->getCode());
    }

    public function testGetCodeDifferentCodes(): void
    {
        $this->assertEquals('201', (new Response(code: '201'))->getCode());
        $this->assertEquals('400', (new Response(code: '400'))->getCode());
        $this->assertEquals('500', (new Response(code: '500'))->getCode());
    }

    public function testDefaultValues(): void
    {
        $response = new Response();
        $this->assertEquals('', $response->getCode());

        $result = $response->toArray();
        $this->assertEquals('', $result['description']);
    }

    public function testContentRefStructure(): void
    {
        $response = new Response(code: '200', contentRef: 'MyResponse');
        $result = $response->toArray();

        $this->assertEquals([
            'description' => '',
            'content' => [
                'application/json' => [
                    'schema' => [
                        '$ref' => '#/components/schemas/MyResponse',
                    ],
                ],
            ],
        ], $result);
    }
}
