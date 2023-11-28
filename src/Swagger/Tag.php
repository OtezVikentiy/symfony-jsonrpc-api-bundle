<?php

namespace OV\JsonRPCAPIBundle\Swagger;

class Tag
{
    public function __construct(
        private readonly string $name = '',
        private readonly string $description = '',
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
        ];
    }

    public function getName(): string
    {
        return $this->name;
    }
}