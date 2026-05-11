<?php

namespace OV\JsonRPCAPIBundle\Tests\DependencyInjection\MethodSpec;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use PHPUnit\Framework\TestCase;

final class RequestMetadataTest extends TestCase
{
    public function testGettersExposeConstructorValues(): void
    {
        $metadata = new RequestMetadata(
            request: 'App\\RPC\\V1\\Foo\\Request',
            allParameters: [['name' => 'a', 'type' => 'int']],
            requiredParameters: [['name' => 'a', 'type' => 'int']],
            requestGetters: ['a' => 'getA'],
            requestSetters: ['a' => 'setA'],
            requestAdders: ['b' => 'addB'],
            validators: ['a' => ['allowsNull' => false, 'type' => 'int']],
        );

        $this->assertSame('App\\RPC\\V1\\Foo\\Request', $metadata->getRequest());
        $this->assertSame([['name' => 'a', 'type' => 'int']], $metadata->getAllParameters());
        $this->assertSame([['name' => 'a', 'type' => 'int']], $metadata->getRequiredParameters());
        $this->assertSame(['a' => 'getA'], $metadata->getRequestGetters());
        $this->assertSame(['a' => 'setA'], $metadata->getRequestSetters());
        $this->assertSame(['b' => 'addB'], $metadata->getRequestAdders());
        $this->assertSame(['a' => ['allowsNull' => false, 'type' => 'int']], $metadata->getValidators());
    }

    public function testNullRequestIsAllowed(): void
    {
        $metadata = new RequestMetadata(
            request: null,
            allParameters: [],
            requiredParameters: [],
            requestGetters: [],
            requestSetters: [],
            requestAdders: [],
            validators: [],
        );

        $this->assertNull($metadata->getRequest());
    }
}
