<?php

namespace OV\JsonRPCAPIBundle\Tests\Swagger\Informational;

use OV\JsonRPCAPIBundle\Swagger\Informational\Contact;
use OV\JsonRPCAPIBundle\Swagger\Informational\Info;
use OV\JsonRPCAPIBundle\Swagger\Informational\License;
use PHPUnit\Framework\TestCase;

final class InfoTest extends TestCase
{
    public function testToArrayWithContactAndLicense(): void
    {
        $contact = new Contact(name: 'Support', url: 'https://example.com', email: 'support@example.com');
        $license = new License(name: 'MIT', url: 'https://opensource.org/licenses/MIT');

        $info = new Info(
            title: 'My API',
            description: 'API description',
            termsOfService: 'https://example.com/tos',
            version: '1',
            contact: $contact,
            license: $license,
        );

        $result = $info->toArray();

        $this->assertEquals('My API', $result['title']);
        $this->assertEquals('API description', $result['description']);
        $this->assertEquals('https://example.com/tos', $result['termsOfService']);
        $this->assertEquals('1', $result['version']);

        $this->assertEquals('Support', $result['contact']['name']);
        $this->assertEquals('https://example.com', $result['contact']['url']);
        $this->assertEquals('support@example.com', $result['contact']['email']);

        $this->assertEquals('MIT', $result['license']['name']);
        $this->assertEquals('https://opensource.org/licenses/MIT', $result['license']['url']);
    }

    public function testToArrayWithDefaultValues(): void
    {
        $info = new Info();
        $result = $info->toArray();

        $this->assertEquals('', $result['title']);
        $this->assertEquals('', $result['description']);
        $this->assertEquals('', $result['termsOfService']);
        $this->assertEquals('', $result['version']);
        $this->assertNull($result['contact']['name']);
        $this->assertNull($result['license']['name']);
    }

    public function testToArrayStructure(): void
    {
        $info = new Info(title: 'Test');
        $result = $info->toArray();

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('termsOfService', $result);
        $this->assertArrayHasKey('version', $result);
        $this->assertArrayHasKey('contact', $result);
        $this->assertArrayHasKey('license', $result);
    }
}
