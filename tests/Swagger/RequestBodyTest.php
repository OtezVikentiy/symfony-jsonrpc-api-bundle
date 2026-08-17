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

    public function testMultipartBodyReplacesTheJsonContent(): void
    {
        $body = new RequestBody(
            contentRef: 'uploadMainRequest',
            fileParts: [['name' => 'file', 'required' => true], ['name' => 'thumb', 'required' => false]],
            multipart: true,
        );

        $content = $body->toArray()['content'];

        $this->assertArrayNotHasKey('application/json', $content);
        $this->assertSame('object', $content['multipart/form-data']['schema']['type']);
        $this->assertSame(
            ['jsonrpc', 'file'],
            $content['multipart/form-data']['schema']['required'],
        );
        $this->assertSame(
            ['type' => 'string', 'format' => 'binary'],
            $content['multipart/form-data']['schema']['properties']['thumb'],
        );
    }

    public function testMultipartEnvelopeFieldPointsAtTheRequestSchema(): void
    {
        $body = new RequestBody(contentRef: 'uploadMainRequest', multipart: true);

        $envelope = $body->toArray()['content']['multipart/form-data']['schema']['properties']['jsonrpc'];

        $this->assertSame('string', $envelope['type']);
        $this->assertStringContainsString('uploadMainRequest', $envelope['description']);
    }

    public function testFilePartsAreIgnoredWhenTheBodyIsNotMultipart(): void
    {
        $body = new RequestBody(contentRef: 'MyRequest', fileParts: [['name' => 'file', 'required' => true]]);

        $this->assertArrayHasKey('application/json', $body->toArray()['content']);
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
