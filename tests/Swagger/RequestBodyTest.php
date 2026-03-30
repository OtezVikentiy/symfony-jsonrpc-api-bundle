<?php

namespace OV\JsonRPCAPIBundle\Tests\Swagger;

use OV\JsonRPCAPIBundle\Swagger\RequestBody;
use PHPUnit\Framework\TestCase;

final class RequestBodyTest extends TestCase
{
    public function testToArray(): void
    {
        $body = new RequestBody(contentRef: 'TestMainRequest', description: 'Request body');
        $result = $body->toArray();

        $this->assertArrayHasKey('description', $result);
        $this->assertEquals('Request body', $result['description']);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('application/json', $result['content']);
        $this->assertEquals(
            '#/components/schemas/TestMainRequest',
            $result['content']['application/json']['schema']['$ref']
        );
    }

    public function testDefaultValues(): void
    {
        $body = new RequestBody();
        $result = $body->toArray();

        $this->assertEquals('', $result['description']);
        $this->assertEquals(
            '#/components/schemas/',
            $result['content']['application/json']['schema']['$ref']
        );
    }

    public function testContentRefStructure(): void
    {
        $body = new RequestBody(contentRef: 'MyRequest');
        $result = $body->toArray();

        $this->assertEquals([
            'description' => '',
            'content' => [
                'application/json' => [
                    'schema' => [
                        '$ref' => '#/components/schemas/MyRequest',
                    ],
                ],
            ],
        ], $result);
    }
}
