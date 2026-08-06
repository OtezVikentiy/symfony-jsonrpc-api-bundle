<?php

namespace OV\JsonRPCAPIBundle\Tests\Security\Fixtures;

final class ScalarConstructorDto
{
    public function __construct(private readonly int $amount)
    {
    }

    public function getAmount(): int
    {
        return $this->amount;
    }
}
