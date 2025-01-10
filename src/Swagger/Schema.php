<?php

namespace OV\JsonRPCAPIBundle\Swagger;

final class Schema
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

        $arr = [
            'type' => $this->type,
            'properties' => $properties,
        ];

        if (!empty($this->required)) {
            $arr['required'] = $this->required;
        }

        return $arr;
    }
}