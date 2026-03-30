<?php

namespace OV\JsonRPCAPIBundle\Tests\Core\Annotation;

use OV\JsonRPCAPIBundle\Core\Annotation\SwaggerArrayProperty;
use PHPUnit\Framework\TestCase;

final class SwaggerArrayPropertyTest extends TestCase
{
    public function testConstructorWithTypeAndOfClass(): void
    {
        $prop = new SwaggerArrayProperty(type: 'Product', ofClass: true);

        $this->assertEquals('Product', $prop->getType());
        $this->assertTrue($prop->isOfClass());
    }

    public function testDefaultOfClassIsFalse(): void
    {
        $prop = new SwaggerArrayProperty(type: 'string');

        $this->assertEquals('string', $prop->getType());
        $this->assertFalse($prop->isOfClass());
    }

    public function testWithIntegerType(): void
    {
        $prop = new SwaggerArrayProperty(type: 'integer');

        $this->assertEquals('integer', $prop->getType());
    }

    public function testIsAttribute(): void
    {
        $reflection = new \ReflectionClass(SwaggerArrayProperty::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertNotEmpty($attributes);
    }
}
