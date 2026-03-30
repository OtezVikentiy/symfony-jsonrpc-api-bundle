<?php

namespace OV\JsonRPCAPIBundle\Tests\Core\Request;

use OV\JsonRPCAPIBundle\Core\Request\JsonRpcRequest;
use PHPUnit\Framework\TestCase;

// Test implementations for JsonRpcRequest abstract class
class SimpleTestRequest extends JsonRpcRequest
{
    private string $name = 'test';
    private int $age = 25;

    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getAge(): int { return $this->age; }
    public function setAge(int $age): void { $this->age = $age; }
}

class BoolTestRequest extends JsonRpcRequest
{
    private bool $active = true;
    private string $title = 'hello';

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): void { $this->active = $active; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): void { $this->title = $title; }
}

class NestedChildObject
{
    private string $value = 'child_value';
    public function getValue(): string { return $this->value; }
    public function setValue(string $value): void { $this->value = $value; }
}

class NestedTestRequest extends JsonRpcRequest
{
    private string $name = 'parent';
    private NestedChildObject $child;

    public function __construct()
    {
        $this->child = new NestedChildObject();
    }

    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getChild(): NestedChildObject { return $this->child; }
    public function setChild(NestedChildObject $child): void { $this->child = $child; }
}

class ArrayTestRequest extends JsonRpcRequest
{
    private array $items = ['a', 'b', 'c'];

    public function getItems(): array { return $this->items; }
    public function setItems(array $items): void { $this->items = $items; }
}

class UninitializedTestRequest extends JsonRpcRequest
{
    private string $initialized = 'yes';
    private string $uninitialized;

    public function getInitialized(): string { return $this->initialized; }
    public function setInitialized(string $initialized): void { $this->initialized = $initialized; }
}

class DateTimeTestRequest extends JsonRpcRequest
{
    private \DateTime $date;

    public function __construct()
    {
        $this->date = new \DateTime('2024-01-01');
    }

    public function getDate(): \DateTime { return $this->date; }
    public function setDate(\DateTime $date): void { $this->date = $date; }
}

class ToArrayChildObject
{
    private string $key = 'val';

    public function toArray(): array
    {
        return ['custom_key' => $this->key];
    }

    public function getKey(): string { return $this->key; }
}

class ToArrayTestRequest extends JsonRpcRequest
{
    private ToArrayChildObject $child;

    public function __construct()
    {
        $this->child = new ToArrayChildObject();
    }

    public function getChild(): ToArrayChildObject { return $this->child; }
    public function setChild(ToArrayChildObject $child): void { $this->child = $child; }
}

final class JsonRpcRequestTest extends TestCase
{
    public function testToArrayWithSimpleProperties(): void
    {
        $request = new SimpleTestRequest();
        $result = $request->toArray();

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('age', $result);
        $this->assertEquals('test', $result['name']);
        $this->assertEquals(25, $result['age']);
    }

    public function testToArrayWithBooleanProperty(): void
    {
        $request = new BoolTestRequest();
        $result = $request->toArray();

        $this->assertArrayHasKey('active', $result);
        $this->assertTrue($result['active']);
        $this->assertArrayHasKey('title', $result);
        $this->assertEquals('hello', $result['title']);
    }

    public function testToArrayWithNestedObject(): void
    {
        $request = new NestedTestRequest();
        $result = $request->toArray();

        $this->assertArrayHasKey('name', $result);
        $this->assertEquals('parent', $result['name']);
        $this->assertArrayHasKey('child', $result);
        $this->assertIsArray($result['child']);
        $this->assertEquals('child_value', $result['child']['value']);
    }

    public function testToArrayWithArrayProperty(): void
    {
        $request = new ArrayTestRequest();
        $result = $request->toArray();

        $this->assertArrayHasKey('items', $result);
        $this->assertEquals(['a', 'b', 'c'], $result['items']);
    }

    public function testToArraySkipsUninitializedProperties(): void
    {
        $request = new UninitializedTestRequest();
        $result = $request->toArray();

        $this->assertArrayHasKey('initialized', $result);
        $this->assertEquals('yes', $result['initialized']);
        $this->assertArrayNotHasKey('uninitialized', $result);
    }

    public function testToArrayWithDateTime(): void
    {
        $request = new DateTimeTestRequest();
        $result = $request->toArray();

        $this->assertArrayHasKey('date', $result);
        $this->assertInstanceOf(\DateTime::class, $result['date']);
    }

    public function testToArrayWithObjectHavingToArrayMethod(): void
    {
        $request = new ToArrayTestRequest();
        $result = $request->toArray();

        $this->assertArrayHasKey('child', $result);
        $this->assertIsArray($result['child']);
        $this->assertEquals(['custom_key' => 'val'], $result['child']);
    }

    public function testToArrayWithModifiedValues(): void
    {
        $request = new SimpleTestRequest();
        $request->setName('modified');
        $request->setAge(30);

        $result = $request->toArray();

        $this->assertEquals('modified', $result['name']);
        $this->assertEquals(30, $result['age']);
    }
}
