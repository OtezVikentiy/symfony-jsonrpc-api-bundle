<?php

namespace OV\JsonRPCAPIBundle\Tests\Swagger;

use OV\JsonRPCAPIBundle\Swagger\Server;
use PHPUnit\Framework\TestCase;

final class ServerTest extends TestCase
{
    public function testToArray(): void
    {
        $server = new Server(url: 'https://api.example.com/api/v1', description: 'Production');
        $result = $server->toArray();

        $this->assertEquals([
            'url' => 'https://api.example.com/api/v1',
            'description' => 'Production',
        ], $result);
    }

    public function testDefaultValues(): void
    {
        $server = new Server();
        $result = $server->toArray();

        $this->assertEquals('', $result['url']);
        $this->assertEquals('', $result['description']);
    }

    public function testWithOnlyUrl(): void
    {
        $server = new Server(url: 'https://test.example.com');
        $result = $server->toArray();

        $this->assertEquals('https://test.example.com', $result['url']);
        $this->assertEquals('', $result['description']);
    }
}
