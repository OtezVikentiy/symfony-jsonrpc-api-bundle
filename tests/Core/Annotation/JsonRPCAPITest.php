<?php

namespace OV\JsonRPCAPIBundle\Tests\Core\Annotation;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use PHPUnit\Framework\TestCase;

final class JsonRPCAPITest extends TestCase
{
    public function testConstructorWithAllParameters(): void
    {
        $api = new JsonRPCAPI(
            methodName: 'getProducts',
            type: 'POST',
            version: 1,
            summary: 'Get products',
            tags: ['products'],
            description: 'Fetches all products',
            ignoreInSwagger: true,
            roles: ['ROLE_USER', 'ROLE_ADMIN'],
            group: 'products',
        );

        $this->assertEquals('getProducts', $api->getMethodName());
        $this->assertEquals('POST', $api->getType());
        $this->assertEquals(1, $api->getVersion());
        $this->assertEquals('Get products', $api->getSummary());
        $this->assertEquals(['products'], $api->getTags());
        $this->assertEquals('Fetches all products', $api->getDescription());
        $this->assertTrue($api->isIgnoreInSwagger());
        $this->assertEquals(['ROLE_USER', 'ROLE_ADMIN'], $api->getRoles());
        $this->assertEquals('products', $api->getGroup());
    }

    public function testDefaultValues(): void
    {
        $api = new JsonRPCAPI(
            methodName: 'test',
            type: 'POST',
        );

        $this->assertEquals('test', $api->getMethodName());
        $this->assertEquals('POST', $api->getType());
        $this->assertNull($api->getVersion());
        $this->assertEquals('', $api->getSummary());
        $this->assertNull($api->getTags());
        $this->assertEquals('', $api->getDescription());
        $this->assertFalse($api->isIgnoreInSwagger());
        $this->assertEquals([], $api->getRoles());
        $this->assertNull($api->getGroup());
    }

    public function testWithVersionNull(): void
    {
        $api = new JsonRPCAPI(methodName: 'test', type: 'GET');

        $this->assertNull($api->getVersion());
    }

    public function testWithDifferentHttpTypes(): void
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $type) {
            $api = new JsonRPCAPI(methodName: 'test', type: $type);
            $this->assertEquals($type, $api->getType());
        }
    }

    public function testWithEmptyRoles(): void
    {
        $api = new JsonRPCAPI(methodName: 'test', type: 'POST', roles: []);

        $this->assertEquals([], $api->getRoles());
    }

    public function testWithMultipleTags(): void
    {
        $api = new JsonRPCAPI(methodName: 'test', type: 'POST', tags: ['math', 'calculations', 'utils']);

        $this->assertEquals(['math', 'calculations', 'utils'], $api->getTags());
    }

    public function testIsAttribute(): void
    {
        $reflection = new \ReflectionClass(JsonRPCAPI::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertNotEmpty($attributes);
    }

    public function testGroupParameter(): void
    {
        $api = new JsonRPCAPI(methodName: 'getProduct', type: 'POST', group: 'products');

        $this->assertEquals('products', $api->getGroup());
    }

    public function testGroupDefaultNull(): void
    {
        $api = new JsonRPCAPI(methodName: 'test', type: 'POST');

        $this->assertNull($api->getGroup());
    }
}
