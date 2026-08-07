<?php

namespace OV\JsonRPCAPIBundle\RPC\V1\Handler;

final class Tag
{
    private string $name = '';

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
