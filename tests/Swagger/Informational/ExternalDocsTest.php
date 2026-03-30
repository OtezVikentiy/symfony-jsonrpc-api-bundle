<?php

namespace OV\JsonRPCAPIBundle\Tests\Swagger\Informational;

use OV\JsonRPCAPIBundle\Swagger\Informational\ExternalDocs;
use PHPUnit\Framework\TestCase;

final class ExternalDocsTest extends TestCase
{
    public function testGetters(): void
    {
        $docs = new ExternalDocs(description: 'Find out more', url: 'https://docs.example.com');

        $this->assertEquals('Find out more', $docs->getDescription());
        $this->assertEquals('https://docs.example.com', $docs->getUrl());
    }

    public function testDefaultValues(): void
    {
        $docs = new ExternalDocs();

        $this->assertEquals('', $docs->getDescription());
        $this->assertEquals('', $docs->getUrl());
    }
}
