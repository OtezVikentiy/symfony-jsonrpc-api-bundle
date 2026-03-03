<?php

namespace OV\JsonRPCAPIBundle\RPC\V1\CreateSome;

class Token
{
    private string $name;
    private string $value;
    private string $summary;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): Token
    {
        $this->name = $name;
        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): Token
    {
        $this->value = $value;
        return $this;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function setSummary(string $summary): Token
    {
        $this->summary = $summary;
        return $this;
    }
}