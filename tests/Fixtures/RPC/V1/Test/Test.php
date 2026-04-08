<?php

namespace OV\JsonRPCAPIBundle\RPC\V1\Test;

class Test
{
    public function __construct(
        private string $name,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): Test
    {
        $this->name = $name;

        return $this;
    }
}