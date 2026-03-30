<?php

namespace OV\JsonRPCAPIBundle\Tests\Swagger;

use OV\JsonRPCAPIBundle\Swagger\SchemaItem;
use PHPUnit\Framework\TestCase;

final class SchemaItemTest extends TestCase
{
    public function testBasicToArray(): void
    {
        $item = new SchemaItem(type: 'string');
        $result = $item->toArray();

        $this->assertEquals(['type' => 'string'], $result);
    }

    public function testWithMinItems(): void
    {
        $item = new SchemaItem(type: 'string', minItems: 1);
        $result = $item->toArray();

        $this->assertEquals(1, $result['minItems']);
    }

    public function testWithMaxItems(): void
    {
        $item = new SchemaItem(type: 'string', maxItems: 10);
        $result = $item->toArray();

        $this->assertEquals(10, $result['maxItems']);
    }

    public function testWithRef(): void
    {
        $item = new SchemaItem(type: 'object', ref: 'Product');
        $result = $item->toArray();

        $this->assertEquals('#/components/schemas/Product', $result['$ref']);
    }

    public function testAddItem(): void
    {
        $item = new SchemaItem(type: 'array');
        $child = new SchemaItem(type: 'string');
        $item->addItem($child);

        $result = $item->toArray();
        $this->assertArrayHasKey('items', $result);
    }

    public function testWithAllFields(): void
    {
        $item = new SchemaItem(type: 'array', minItems: 1, maxItems: 100, ref: 'Item');
        $result = $item->toArray();

        $this->assertEquals('array', $result['type']);
        $this->assertEquals(1, $result['minItems']);
        $this->assertEquals(100, $result['maxItems']);
        $this->assertEquals('#/components/schemas/Item', $result['$ref']);
    }

    public function testOmitsNullFields(): void
    {
        $item = new SchemaItem(type: 'integer');
        $result = $item->toArray();

        $this->assertArrayNotHasKey('minItems', $result);
        $this->assertArrayNotHasKey('maxItems', $result);
        $this->assertArrayNotHasKey('$ref', $result);
        $this->assertArrayNotHasKey('items', $result);
    }
}
