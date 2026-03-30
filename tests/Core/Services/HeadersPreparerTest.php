<?php

namespace OV\JsonRPCAPIBundle\Tests\Core\Services;

use OV\JsonRPCAPIBundle\Core\Services\HeadersPreparer;
use PHPUnit\Framework\TestCase;

final class HeadersPreparerTest extends TestCase
{
    public function testSingleOrigin(): void
    {
        $preparer = new HeadersPreparer(['https://example.com']);
        $headers = $preparer->prepareHeaders();

        $this->assertArrayHasKey('Access-Control-Allow-Origin', $headers);
        $this->assertEquals('https://example.com', $headers['Access-Control-Allow-Origin']);
    }

    public function testMultipleOrigins(): void
    {
        $preparer = new HeadersPreparer(['https://example.com', 'https://app.example.com']);
        $headers = $preparer->prepareHeaders();

        $this->assertEquals('https://example.com, https://app.example.com', $headers['Access-Control-Allow-Origin']);
    }

    public function testWildcardOrigin(): void
    {
        $preparer = new HeadersPreparer(['*']);
        $headers = $preparer->prepareHeaders();

        $this->assertEquals('*', $headers['Access-Control-Allow-Origin']);
    }

    public function testEmptyOriginList(): void
    {
        $preparer = new HeadersPreparer([]);
        $headers = $preparer->prepareHeaders();

        $this->assertEquals('', $headers['Access-Control-Allow-Origin']);
    }

    public function testHeadersReturnArray(): void
    {
        $preparer = new HeadersPreparer(['*']);
        $headers = $preparer->prepareHeaders();

        $this->assertIsArray($headers);
        $this->assertCount(1, $headers);
    }
}
