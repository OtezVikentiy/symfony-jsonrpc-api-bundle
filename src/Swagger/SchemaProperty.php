<?php

namespace OV\JsonRPCAPIBundle\Swagger;

class SchemaProperty
{
    public function __construct(
        private readonly string $name = '',
        private readonly string $type = '',
        private readonly string $format = '',
        private readonly string $example = '',
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'format' => $this->format,
            'example' => $this->example,
        ];
    }
}