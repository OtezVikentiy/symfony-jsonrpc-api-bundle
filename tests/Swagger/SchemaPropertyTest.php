<?php

namespace OV\JsonRPCAPIBundle\Tests\Swagger;

use OV\JsonRPCAPIBundle\Swagger\SchemaItem;
use OV\JsonRPCAPIBundle\Swagger\SchemaProperty;
use PHPUnit\Framework\TestCase;

final class SchemaPropertyTest extends TestCase
{
    public function testToArrayWithoutRef(): void
    {
        $property = new SchemaProperty(name: 'title', type: 'string');
        $result = $property->toArray();

        $this->assertEquals(['type' => 'string'], $result);
    }

    public function testToArrayWithRef(): void
    {
        $property = new SchemaProperty(name: 'params', ref: 'TestRequest');
        $result = $property->toArray();

        $this->assertEquals(['$ref' => '#/components/schemas/TestRequest'], $result);
    }

    public function testToArrayWithExample(): void
    {
        $property = new SchemaProperty(name: 'version', type: 'string', example: '2.0');
        $result = $property->toArray();

        $this->assertEquals('2.0', $result['example']);
    }

    public function testToArrayWithDefault(): void
    {
        $property = new SchemaProperty(name: 'version', type: 'string', default: '2.0');
        $result = $property->toArray();

        $this->assertEquals('2.0', $result['default']);
    }

    public function testToArrayWithFormat(): void
    {
        $property = new SchemaProperty(name: 'email', type: 'string', format: 'email');
        $result = $property->toArray();

        $this->assertEquals('email', $result['format']);
    }

    public function testToArrayWithItems(): void
    {
        $items = new SchemaItem(type: 'string');
        $property = new SchemaProperty(name: 'tags', type: 'array', items: $items);
        $result = $property->toArray();

        $this->assertEquals('array', $result['type']);
        $this->assertArrayHasKey('items', $result);
        $this->assertEquals('string', $result['items']['type']);
    }

    public function testToArrayWithAllFields(): void
    {
        $property = new SchemaProperty(
            name: 'jsonrpc',
            type: 'string',
            default: '2.0',
            format: 'version',
            example: '2.0',
        );
        $result = $property->toArray();

        $this->assertEquals('string', $result['type']);
        $this->assertEquals('2.0', $result['default']);
        $this->assertEquals('version', $result['format']);
        $this->assertEquals('2.0', $result['example']);
    }

    public function testGetName(): void
    {
        $property = new SchemaProperty(name: 'test_name');
        $this->assertEquals('test_name', $property->getName());
    }

    public function testFluentSetters(): void
    {
        $property = new SchemaProperty(name: 'test');

        $result = $property->setType('integer');
        $this->assertSame($property, $result);

        $result = $property->setDefault('0');
        $this->assertSame($property, $result);

        $result = $property->setFormat('int64');
        $this->assertSame($property, $result);

        $result = $property->setExample('42');
        $this->assertSame($property, $result);

        $result = $property->setItems(new SchemaItem(type: 'string'));
        $this->assertSame($property, $result);

        $result = $property->setRef('TestSchema');
        $this->assertSame($property, $result);

        $result = $property->setName('newName');
        $this->assertSame($property, $result);
    }

    public function testRefTakesPrecedenceOverType(): void
    {
        $property = new SchemaProperty(name: 'test', type: 'object', ref: 'MySchema');
        $result = $property->toArray();

        // When ref is set, type is not included, only $ref
        $this->assertArrayHasKey('$ref', $result);
        $this->assertArrayNotHasKey('type', $result);
    }

    public function testOmitsNullFields(): void
    {
        $property = new SchemaProperty(name: 'simple', type: 'string');
        $result = $property->toArray();

        $this->assertArrayNotHasKey('example', $result);
        $this->assertArrayNotHasKey('default', $result);
        $this->assertArrayNotHasKey('format', $result);
        $this->assertArrayNotHasKey('items', $result);
    }
}
