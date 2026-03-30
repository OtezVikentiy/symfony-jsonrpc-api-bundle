<?php

namespace OV\JsonRPCAPIBundle\Tests\DependencyInjection;

use Exception;
use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use PHPUnit\Framework\TestCase;

final class MethodSpecCollectionTest extends TestCase
{
    private function createMethodSpec(string $methodName = 'test'): MethodSpec
    {
        return new MethodSpec(
            methodClass: 'App\\Method\\TestMethod',
            requestType: 'POST',
            summary: '',
            description: '',
            ignoreInSwagger: false,
            methodName: $methodName,
            allParameters: [],
            requiredParameters: [],
            request: null,
            requestGetters: [],
            requestSetters: [],
            requestAdders: [],
            validators: [],
        );
    }

    public function testAddAndGetMethodSpec(): void
    {
        $collection = new MethodSpecCollection();
        $spec = $this->createMethodSpec('test');

        $collection->addMethodSpec('1', 'test', $spec);
        $result = $collection->getMethodSpec('1', 'test');

        $this->assertSame($spec, $result);
    }

    public function testAddDuplicateThrowsException(): void
    {
        $collection = new MethodSpecCollection();
        $spec1 = $this->createMethodSpec('test');
        $spec2 = $this->createMethodSpec('test');

        $collection->addMethodSpec('1', 'test', $spec1);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Method name test already in use.');

        $collection->addMethodSpec('1', 'test', $spec2);
    }

    public function testGetNonExistentMethodThrowsJRPCException(): void
    {
        $collection = new MethodSpecCollection();

        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::METHOD_NOT_FOUND);

        $collection->getMethodSpec('1', 'nonexistent');
    }

    public function testGetAllMethods(): void
    {
        $collection = new MethodSpecCollection();
        $spec1 = $this->createMethodSpec('method1');
        $spec2 = $this->createMethodSpec('method2');

        $collection->addMethodSpec('1', 'method1', $spec1);
        $collection->addMethodSpec('1', 'method2', $spec2);

        $all = $collection->getAllMethods();

        $this->assertIsArray($all);
        $this->assertArrayHasKey('1', $all);
        $this->assertCount(2, $all['1']);
    }

    public function testGetMethodNames(): void
    {
        $collection = new MethodSpecCollection();
        $spec1 = $this->createMethodSpec('method1');
        $spec2 = $this->createMethodSpec('method2');

        $collection->addMethodSpec('1', 'method1', $spec1);
        $collection->addMethodSpec('2', 'method2', $spec2);

        $names = $collection->getMethodNames();

        // getMethodNames returns the version keys
        $this->assertContains(1, $names);
        $this->assertContains(2, $names);
    }

    public function testMultipleVersions(): void
    {
        $collection = new MethodSpecCollection();
        $specV1 = $this->createMethodSpec('test');
        $specV2 = $this->createMethodSpec('test');

        $collection->addMethodSpec('1', 'test', $specV1);
        $collection->addMethodSpec('2', 'test', $specV2);

        $this->assertSame($specV1, $collection->getMethodSpec('1', 'test'));
        $this->assertSame($specV2, $collection->getMethodSpec('2', 'test'));
    }

    public function testSameMethodNameDifferentVersions(): void
    {
        $collection = new MethodSpecCollection();
        $specV1 = $this->createMethodSpec('subtract');
        $specV2 = $this->createMethodSpec('subtract');

        $collection->addMethodSpec('1', 'subtract', $specV1);
        $collection->addMethodSpec('2', 'subtract', $specV2);

        $this->assertNotSame(
            $collection->getMethodSpec('1', 'subtract'),
            $collection->getMethodSpec('2', 'subtract')
        );
    }

    public function testGetMethodSpecWrongVersion(): void
    {
        $collection = new MethodSpecCollection();
        $spec = $this->createMethodSpec('test');
        $collection->addMethodSpec('1', 'test', $spec);

        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::METHOD_NOT_FOUND);

        $collection->getMethodSpec('2', 'test');
    }

    public function testEmptyCollection(): void
    {
        $collection = new MethodSpecCollection();

        $this->assertEquals([], $collection->getAllMethods());
        $this->assertEquals([], $collection->getMethodNames());
    }
}
