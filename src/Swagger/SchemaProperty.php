<?php

namespace OV\JsonRPCAPIBundle\Swagger;

readonly class SchemaProperty
{
    public function __construct(
        private string $name = '',
        private string $type = '',
        private string $default = '',
        private string $format = '',
        private ?string $example = '',
        private ?string $ref = null,
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