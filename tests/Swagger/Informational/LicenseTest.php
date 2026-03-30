<?php

namespace OV\JsonRPCAPIBundle\Tests\Swagger\Informational;

use OV\JsonRPCAPIBundle\Swagger\Informational\License;
use PHPUnit\Framework\TestCase;

final class LicenseTest extends TestCase
{
    public function testGetters(): void
    {
        $license = new License(name: 'MIT', url: 'https://opensource.org/licenses/MIT');

        $this->assertEquals('MIT', $license->getName());
        $this->assertEquals('https://opensource.org/licenses/MIT', $license->getUrl());
    }

    public function testDefaultValues(): void
    {
        $license = new License();

        $this->assertEquals('', $license->getName());
        $this->assertEquals('', $license->getUrl());
    }
}
