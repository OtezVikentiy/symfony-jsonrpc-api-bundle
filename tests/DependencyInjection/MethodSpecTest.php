<?php

namespace OV\JsonRPCAPIBundle\Tests\DependencyInjection;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use PHPUnit\Framework\TestCase;

final class MethodSpecTest extends TestCase
{
    private function createMethodSpec(array $overrides = []): MethodSpec
    {
        return new MethodSpec(
            methodClass: $overrides['methodClass'] ?? 'App\\Method\\TestMethod',
            requestType: $overrides['requestType'] ?? 'POST',
            summary: $overrides['summary'] ?? 'Test summary',
            description: $overrides['description'] ?? 'Test description',
            ignoreInSwagger: $overrides['ignoreInSwagger'] ?? false,
            methodName: $overrides['methodName'] ?? 'testMethod',
            allParameters: $overrides['allParameters'] ?? [['name' => 'id', 'type' => 'int']],
            requiredParameters: $overrides['requiredParameters'] ?? [['name' => 'id', 'type' => 'int']],
            request: $overrides['request'] ?? 'App\\Method\\TestRequest',
            requestGetters: $overrides['requestGetters'] ?? ['id' => 'getId'],
            requestSetters: $overrides['requestSetters'] ?? ['id' => 'setId'],
            requestAdders: $overrides['requestAdders'] ?? [],
            validators: $overrides['validators'] ?? ['id' => ['allowsNull' => false, 'type' => 'int']],
            roles: $overrides['roles'] ?? [],
            tags: $overrides['tags'] ?? ['test'],
            plainResponse: $overrides['plainResponse'] ?? false,
            preProcessorExists: $overrides['preProcessorExists'] ?? false,
            postProcessorExists: $overrides['postProcessorExists'] ?? false,
        );
    }

    public function testGetMethodClass(): void
    {
        $spec = $this->createMethodSpec(['methodClass' => 'App\\RPC\\V1\\MyMethod']);
        $this->assertEquals('App\\RPC\\V1\\MyMethod', $spec->getMethodClass());
    }

    public function testGetRequestType(): void
    {
        $spec = $this->createMethodSpec(['requestType' => 'PUT']);
        $this->assertEquals('PUT', $spec->getRequestType());
    }

    public function testGetSummary(): void
    {
        $spec = $this->createMethodSpec(['summary' => 'My summary']);
        $this->assertEquals('My summary', $spec->getSummary());
    }

    public function testGetDescription(): void
    {
        $spec = $this->createMethodSpec(['description' => 'My description']);
        $this->assertEquals('My description', $spec->getDescription());
    }

    public function testIsIgnoreInSwagger(): void
    {
        $spec = $this->createMethodSpec(['ignoreInSwagger' => true]);
        $this->assertTrue($spec->isIgnoreInSwagger());
    }

    public function testGetMethodName(): void
    {
        $spec = $this->createMethodSpec(['methodName' => 'getProducts']);
        $this->assertEquals('getProducts', $spec->getMethodName());
    }

    public function testGetAllParameters(): void
    {
        $params = [['name' => 'id', 'type' => 'int'], ['name' => 'title', 'type' => 'string']];
        $spec = $this->createMethodSpec(['allParameters' => $params]);
        $this->assertEquals($params, $spec->getAllParameters());
    }

    public function testGetRequiredParameters(): void
    {
        $params = [['name' => 'id', 'type' => 'int']];
        $spec = $this->createMethodSpec(['requiredParameters' => $params]);
        $this->assertEquals($params, $spec->getRequiredParameters());
    }

    public function testGetRequest(): void
    {
        $spec = $this->createMethodSpec(['request' => 'App\\Request\\TestRequest']);
        $this->assertEquals('App\\Request\\TestRequest', $spec->getRequest());
    }

    public function testGetRequestNull(): void
    {
        $spec = new MethodSpec(
            methodClass: 'App\\Method\\TestMethod',
            requestType: 'POST',
            summary: '',
            description: '',
            ignoreInSwagger: false,
            methodName: 'test',
            allParameters: [],
            requiredParameters: [],
            request: null,
            requestGetters: [],
            requestSetters: [],
            requestAdders: [],
            validators: [],
        );
        $this->assertNull($spec->getRequest());
    }

    public function testGetRequestGetters(): void
    {
        $getters = ['id' => 'getId', 'title' => 'getTitle'];
        $spec = $this->createMethodSpec(['requestGetters' => $getters]);
        $this->assertEquals($getters, $spec->getRequestGetters());
    }

    public function testGetRequestSetters(): void
    {
        $setters = ['id' => 'setId', 'title' => 'setTitle'];
        $spec = $this->createMethodSpec(['requestSetters' => $setters]);
        $this->assertEquals($setters, $spec->getRequestSetters());
    }

    public function testGetRequestAdders(): void
    {
        $adders = ['token' => 'addToken'];
        $spec = $this->createMethodSpec(['requestAdders' => $adders]);
        $this->assertEquals($adders, $spec->getRequestAdders());
    }

    public function testGetValidators(): void
    {
        $validators = ['id' => ['allowsNull' => false, 'type' => 'int']];
        $spec = $this->createMethodSpec(['validators' => $validators]);
        $this->assertEquals($validators, $spec->getValidators());
    }

    public function testGetRoles(): void
    {
        $spec = $this->createMethodSpec(['roles' => ['ROLE_USER', 'ROLE_ADMIN']]);
        $this->assertEquals(['ROLE_USER', 'ROLE_ADMIN'], $spec->getRoles());
    }

    public function testGetRolesDefaultEmpty(): void
    {
        $spec = $this->createMethodSpec();
        $this->assertEquals([], $spec->getRoles());
    }

    public function testGetTags(): void
    {
        $spec = $this->createMethodSpec(['tags' => ['math', 'utils']]);
        $this->assertEquals(['math', 'utils'], $spec->getTags());
    }

    public function testIsPlainResponse(): void
    {
        $spec = $this->createMethodSpec(['plainResponse' => true]);
        $this->assertTrue($spec->isPlainResponse());
    }

    public function testIsPlainResponseDefaultFalse(): void
    {
        $spec = $this->createMethodSpec();
        $this->assertFalse($spec->isPlainResponse());
    }

    public function testIsPreProcessorExists(): void
    {
        $spec = $this->createMethodSpec(['preProcessorExists' => true]);
        $this->assertTrue($spec->isPreProcessorExists());
    }

    public function testIsPreProcessorExistsDefaultFalse(): void
    {
        $spec = $this->createMethodSpec();
        $this->assertFalse($spec->isPreProcessorExists());
    }

    public function testIsPostProcessorExists(): void
    {
        $spec = $this->createMethodSpec(['postProcessorExists' => true]);
        $this->assertTrue($spec->isPostProcessorExists());
    }

    public function testIsPostProcessorExistsDefaultFalse(): void
    {
        $spec = $this->createMethodSpec();
        $this->assertFalse($spec->isPostProcessorExists());
    }
}
