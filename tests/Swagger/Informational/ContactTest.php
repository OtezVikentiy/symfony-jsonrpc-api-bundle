<?php

namespace OV\JsonRPCAPIBundle\Tests\Swagger\Informational;

use OV\JsonRPCAPIBundle\Swagger\Informational\Contact;
use PHPUnit\Framework\TestCase;

final class ContactTest extends TestCase
{
    public function testGetters(): void
    {
        $contact = new Contact(name: 'Support', url: 'https://example.com', email: 'support@example.com');

        $this->assertEquals('Support', $contact->getName());
        $this->assertEquals('https://example.com', $contact->getUrl());
        $this->assertEquals('support@example.com', $contact->getEmail());
    }

    public function testDefaultValues(): void
    {
        $contact = new Contact();

        $this->assertEquals('', $contact->getName());
        $this->assertEquals('', $contact->getUrl());
        $this->assertEquals('', $contact->getEmail());
    }
}
