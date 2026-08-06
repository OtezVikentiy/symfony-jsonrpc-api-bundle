<?php

namespace OV\JsonRPCAPIBundle\Tests\Security\Fixtures;

final class RequiredConstructorArgDto
{
    public function __construct(private readonly int $requiredArg)
    {
    }

    public function getRequiredArg(): int
    {
        return $this->requiredArg;
    }
}
