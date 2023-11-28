<?php

namespace OV\JsonRPCAPIBundle\Swagger;

class Schema
{
    public function __construct(
        private readonly string $name = '',
        private array $properties = [],
        private array $required = [],
        private readonly string $type = 'object',
    ) {}

    public function addProperty(SchemaProperty $property): void
    {
        $this->properties[] = $property;
    }

    public function addRequired(SchemaProperty $property): void
    {
        $this->required[] = $property->getName();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function toArray(): array
    {
        $properties = [];
        foreach ($this->properties as $property) {
            $properties[$property->getName()] = $property->toArray();
        }

        return [
            'type' => $this->type,
            'properties' => $properties,
            'required' => $this->required,
        ];
    }
}