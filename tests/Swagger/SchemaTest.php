<?php

namespace OV\JsonRPCAPIBundle\Tests\Swagger;

use OV\JsonRPCAPIBundle\Swagger\Schema;
use OV\JsonRPCAPIBundle\Swagger\SchemaProperty;
use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase
{
    public function testEmptySchema(): void
    {
        $schema = new Schema('TestSchema');
        $result = $schema->toArray();

        $this->assertEquals('object', $result['type']);
        $this->assertEquals([], $result['properties']);
        $this->assertArrayNotHasKey('required', $result);
    }

    public function testGetName(): void
    {
        $schema = new Schema('MySchema');
        $this->assertEquals('MySchema', $schema->getName());
    }

    public function testAddProperty(): void
    {
        $schema = new Schema('Test');
        $property = new SchemaProperty(name: 'title', type: 'string');
        $schema->addProperty($property);

        $result = $schema->toArray();
        $this->assertArrayHasKey('title', $result['properties']);
        $this->assertEquals('string', $result['properties']['title']['type']);
    }

    public function testAddRequired(): void
    {
        $schema = new Schema('Test');
        $property = new SchemaProperty(name: 'id', type: 'integer');
        $schema->addProperty($property);
        $schema->addRequired($property);

        $result = $schema->toArray();
        $this->assertArrayHasKey('required', $result);
        $this->assertContains('id', $result['required']);
    }

    public function testMultipleProperties(): void
    {
        $schema = new Schema('Test');
        $schema->addProperty(new SchemaProperty(name: 'id', type: 'integer'));
        $schema->addProperty(new SchemaProperty(name: 'name', type: 'string'));
        $schema->addProperty(new SchemaProperty(name: 'active', type: 'boolean'));

        $result = $schema->toArray();
        $this->assertCount(3, $result['properties']);
    }

    public function testMultipleRequired(): void
    {
        $schema = new Schema('Test');
        $p1 = new SchemaProperty(name: 'id', type: 'integer');
        $p2 = new SchemaProperty(name: 'name', type: 'string');
        $schema->addProperty($p1);
        $schema->addProperty($p2);
        $schema->addRequired($p1);
        $schema->addRequired($p2);

        $result = $schema->toArray();
        $this->assertContains('id', $result['required']);
        $this->assertContains('name', $result['required']);
    }

    public function testDefaultType(): void
    {
        $schema = new Schema('Test');
        $result = $schema->toArray();

        $this->assertEquals('object', $result['type']);
    }

    public function testDefaultName(): void
    {
        $schema = new Schema();
        $this->assertEquals('', $schema->getName());
    }
}
