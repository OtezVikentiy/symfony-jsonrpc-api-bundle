<?php

namespace OV\JsonRPCAPIBundle\Tests\Core\Request;

use OV\JsonRPCAPIBundle\Core\Request\JsonRpcRequest;
use OV\JsonRPCAPIBundle\Core\Request\PartialRequestInterface;
use OV\JsonRPCAPIBundle\Core\Request\PartialUpdateRequest;
use PHPUnit\Framework\TestCase;

final class PartialUpdateRequestTest extends TestCase
{
    public function testSubclassImplementsPartialContract(): void
    {
        $request = new FakePartialRequest();

        $this->assertInstanceOf(JsonRpcRequest::class, $request);
        $this->assertInstanceOf(PartialRequestInterface::class, $request);
    }

    public function testTracksProvidedFields(): void
    {
        $request = new FakePartialRequest();
        $request->markProvided('email');

        $this->assertTrue($request->wasProvided('email'));
        $this->assertFalse($request->wasProvided('bio'));
    }

    public function testToArrayRecursesViaParent(): void
    {
        $request = new FakePartialRequest();
        $request->setEmail('test@example.com');

        $arr = $request->toArray();

        $this->assertSame('test@example.com', $arr['email']);
    }
}

final class FakePartialRequest extends PartialUpdateRequest
{
    private ?string $email = null;
    private ?string $bio = null;

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): void
    {
        $this->bio = $bio;
    }
}
