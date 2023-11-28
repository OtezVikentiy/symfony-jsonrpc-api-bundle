<?php

namespace OV\JsonRPCAPIBundle\Swagger;

class SchemaProperty
{
    public function __construct(
        private readonly string $name = '',
        private readonly string $type = '',
        private readonly string $default = '',
        private readonly string $format = '',
        private readonly ?string $example = '',
        private readonly ?string $ref = null,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function toArray(): array
    {
        if (is_null($this->ref)) {
            return [
                'type' => $this->type,
                'format' => $this->format,
                'default' => $this->default,
                'example' => (in_array($this->type, ['int', 'integer']) && empty($this->example)) ? 0 : $this->example,
            ];
        } else {
            return [
                '$ref' => '#/components/schemas/'.$this->ref,
            ];
        }
    }
}