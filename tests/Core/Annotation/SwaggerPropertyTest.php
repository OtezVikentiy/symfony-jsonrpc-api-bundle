<?php

namespace OV\JsonRPCAPIBundle\Tests\Core\Annotation;

use OV\JsonRPCAPIBundle\Core\Annotation\SwaggerProperty;
use PHPUnit\Framework\TestCase;

final class SwaggerPropertyTest extends TestCase
{
    public function testConstructorWithAllParameters(): void
    {
        $prop = new SwaggerProperty(
            default: '2.0',
            format: 'version',
            example: '2.0',
        );

        $this->assertEquals('2.0', $prop->getDefault());
        $this->assertEquals('version', $prop->getFormat());
        $this->assertEquals('2.0', $prop->getExample());
    }

    public function testDefaultValues(): void
    {
        $prop = new SwaggerProperty();

        $this->assertNull($prop->getDefault());
        $this->assertNull($prop->getFormat());
        $this->assertNull($prop->getExample());
    }

    public function testWithOnlyDefault(): void
    {
        $prop = new SwaggerProperty(default: 'test');

        $this->assertEquals('test', $prop->getDefault());
        $this->assertNull($prop->getFormat());
        $this->assertNull($prop->getExample());
    }

    public function testWithOnlyFormat(): void
    {
        $prop = new SwaggerProperty(format: 'email');

        $this->assertNull($prop->getDefault());
        $this->assertEquals('email', $prop->getFormat());
    }

    public function testWithOnlyExample(): void
    {
        $prop = new SwaggerProperty(example: 'john@example.com');

        $this->assertEquals('john@example.com', $prop->getExample());
    }

    public function testIsAttribute(): void
    {
        $reflection = new \ReflectionClass(SwaggerProperty::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertNotEmpty($attributes);
    }
}
