<?php

namespace OV\JsonRPCAPIBundle\Tests\Swagger\Informational;

use OV\JsonRPCAPIBundle\Swagger\Informational\Contact;
use OV\JsonRPCAPIBundle\Swagger\Informational\Info;
use OV\JsonRPCAPIBundle\Swagger\Informational\License;
use OV\JsonRPCAPIBundle\Swagger\Informational\Openapi;
use OV\JsonRPCAPIBundle\Swagger\Path;
use OV\JsonRPCAPIBundle\Swagger\RequestBody;
use OV\JsonRPCAPIBundle\Swagger\Response;
use OV\JsonRPCAPIBundle\Swagger\Schema;
use OV\JsonRPCAPIBundle\Swagger\SchemaProperty;
use OV\JsonRPCAPIBundle\Swagger\Server;
use PHPUnit\Framework\TestCase;

final class OpenapiTest extends TestCase
{
    private function createOpenapi(?string $securitySchemeName = null, ?array $securityScheme = null): Openapi
    {
        $contact = new Contact(name: 'Support', url: 'https://example.com', email: 'support@example.com');
        $license = new License(name: 'MIT', url: 'https://opensource.org/licenses/MIT');
        $info = new Info(
            title: 'Test API',
            description: 'Test description',
            termsOfService: 'https://example.com/tos',
            version: '1',
            contact: $contact,
            license: $license,
        );

        $servers = [
            new Server(url: 'https://api.example.com/api/v1', description: 'Production'),
            new Server(url: 'https://test.example.com/api/v1', description: 'Test'),
        ];

        $tags = [
            ['name' => 'math'],
            ['name' => 'utils'],
        ];

        $requestBody = new RequestBody(contentRef: 'TestMainRequest');
        $response = new Response(code: '200', contentRef: 'TestResponse');
        $paths = [
            new Path(
                name: '/test_method',
                methodType: 'POST',
                summary: 'Test',
                description: 'Test method',
                requestBody: $requestBody,
                tags: ['math'],
                responses: [$response],
            ),
        ];

        $schema = new Schema('TestMainRequest');
        $schema->addProperty(new SchemaProperty(name: 'jsonrpc', type: 'string'));
        $components = [$schema];

        return new Openapi(
            $info, $servers, $tags, $paths, $components,
            $securitySchemeName, $securityScheme
        );
    }

    public function testToArrayStructure(): void
    {
        $openapi = $this->createOpenapi();
        $result = $openapi->toArray();

        $this->assertArrayHasKey('openapi', $result);
        $this->assertArrayHasKey('info', $result);
        $this->assertArrayHasKey('servers', $result);
        $this->assertArrayHasKey('tags', $result);
        $this->assertArrayHasKey('paths', $result);
        $this->assertArrayHasKey('components', $result);
    }

    public function testOpenapiVersion(): void
    {
        $openapi = $this->createOpenapi();
        $result = $openapi->toArray();

        $this->assertEquals('3.1.1', $result['openapi']);
    }

    public function testInfoSection(): void
    {
        $openapi = $this->createOpenapi();
        $result = $openapi->toArray();

        $this->assertEquals('Test API', $result['info']['title']);
        $this->assertEquals('Test description', $result['info']['description']);
        $this->assertEquals('1', $result['info']['version']);
    }

    public function testServersSection(): void
    {
        $openapi = $this->createOpenapi();
        $result = $openapi->toArray();

        $this->assertCount(2, $result['servers']);
        $this->assertEquals('https://api.example.com/api/v1', $result['servers'][0]['url']);
        $this->assertEquals('Production', $result['servers'][0]['description']);
        $this->assertEquals('https://test.example.com/api/v1', $result['servers'][1]['url']);
    }

    public function testTagsSection(): void
    {
        $openapi = $this->createOpenapi();
        $result = $openapi->toArray();

        $this->assertCount(2, $result['tags']);
        $this->assertEquals('math', $result['tags'][0]['name']);
        $this->assertEquals('utils', $result['tags'][1]['name']);
    }

    public function testPathsSection(): void
    {
        $openapi = $this->createOpenapi();
        $result = $openapi->toArray();

        $this->assertArrayHasKey('/test_method', $result['paths']);
    }

    public function testComponentsSection(): void
    {
        $openapi = $this->createOpenapi();
        $result = $openapi->toArray();

        $this->assertArrayHasKey('schemas', $result['components']);
        $this->assertArrayHasKey('TestMainRequest', $result['components']['schemas']);
    }

    public function testEmptyOpenapi(): void
    {
        $info = new Info();
        $openapi = new Openapi($info, [], [], [], []);
        $result = $openapi->toArray();

        $this->assertEquals('3.1.1', $result['openapi']);
        $this->assertArrayNotHasKey('servers', $result);
        $this->assertArrayNotHasKey('tags', $result);
        $this->assertArrayNotHasKey('paths', $result);
        $this->assertArrayNotHasKey('components', $result);
    }

    public function testSecurityScheme(): void
    {
        $openapi = $this->createOpenapi(
            securitySchemeName: 'ApiKeyAuth',
            securityScheme: ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-AUTH-TOKEN'],
        );
        $result = $openapi->toArray();

        $this->assertArrayHasKey('securitySchemes', $result['components']);
        $this->assertArrayHasKey('ApiKeyAuth', $result['components']['securitySchemes']);
        $this->assertEquals('apiKey', $result['components']['securitySchemes']['ApiKeyAuth']['type']);
        $this->assertEquals('header', $result['components']['securitySchemes']['ApiKeyAuth']['in']);
        $this->assertEquals('X-AUTH-TOKEN', $result['components']['securitySchemes']['ApiKeyAuth']['name']);

        $this->assertArrayHasKey('security', $result);
        $this->assertEquals([['ApiKeyAuth' => []]], $result['security']);
    }

    public function testWithoutSecurityScheme(): void
    {
        $openapi = $this->createOpenapi();
        $result = $openapi->toArray();

        $this->assertArrayNotHasKey('securitySchemes', $result['components'] ?? []);
        $this->assertArrayNotHasKey('security', $result);
    }

    public function testEmptyTagsFiltered(): void
    {
        $info = new Info(title: 'Test');
        $requestBody = new RequestBody(contentRef: 'Test');
        $paths = [new Path(name: '/test', methodType: 'POST', requestBody: $requestBody)];
        $openapi = new Openapi($info, [], [[], ['name' => 'valid'], []], $paths, []);
        $result = $openapi->toArray();

        $this->assertCount(1, $result['tags']);
        $this->assertEquals('valid', $result['tags'][0]['name']);
    }
}
