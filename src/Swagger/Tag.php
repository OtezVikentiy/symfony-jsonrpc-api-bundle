<?php

namespace OV\JsonRPCAPIBundle\Swagger;

readonly class Tag
{
    public function __construct(
        private string $name = '',
        private string $description = '',
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