<?php

namespace OV\JsonRPCAPIBundle\Tests\DependencyInjection\MethodSpec;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use PHPUnit\Framework\TestCase;

final class SwaggerMetadataTest extends TestCase
{
    public function testGettersExposeAllFields(): void
    {
        $metadata = new SwaggerMetadata(
            summary: 'sum',
            description: 'desc',
            ignoreInSwagger: false,
            tags: ['products', 'public'],
            group: 'products',
        );

        $this->assertSame('sum', $metadata->getSummary());
        $this->assertSame('desc', $metadata->getDescription());
        $this->assertFalse($metadata->isIgnoreInSwagger());
        $this->assertSame(['products', 'public'], $metadata->getTags());
        $this->assertSame('products', $metadata->getGroup());
    }

    public function testNullableDefaults(): void
    {
        $metadata = new SwaggerMetadata(
            summary: '',
            description: '',
            ignoreInSwagger: true,
        );

        $this->assertTrue($metadata->isIgnoreInSwagger());
        $this->assertNull($metadata->getTags());
        $this->assertNull($metadata->getGroup());
    }
}
