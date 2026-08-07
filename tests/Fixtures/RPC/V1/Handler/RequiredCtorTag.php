<?php

namespace OV\JsonRPCAPIBundle\RPC\V1\Handler;

/**
 * An element type that cannot be default-constructed. Building one from a payload fragment fails
 * before any setter runs.
 */
final class RequiredCtorTag
{
    public function __construct(private string $name)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
