<?php

namespace OV\JsonRPCAPIBundle\Swagger;

final class SchemaProperty
{
    public function __construct(
        private string $name = '',
        private string $type = '',
        private ?string $default = null,
        private ?string $format = null,
        private ?string $example = null,
        private ?SchemaItem $items = null,
        private ?string $ref = null,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): SchemaProperty
    {
        $this->name = $name;

        return $this;
    }

    public function setType(string $type): SchemaProperty
    {
        $this->type = $type;

        return $this;
    }

    public function setDefault(?string $default): SchemaProperty
    {
        $this->default = $default;

        return $this;
    }

    public function setFormat(?string $format): SchemaProperty
    {
        $this->format = $format;

        return $this;
    }

    public function setExample(?string $example): SchemaProperty
    {
        $this->example = $example;

        return $this;
    }

    public function setItems(?SchemaItem $items): SchemaProperty
    {
        $this->items = $items;

        return $this;
    }

    public function setRef(?string $ref): SchemaProperty
    {
        $this->ref = $ref;

        return $this;
    }

    public function toArray(): array
    {
        if (is_null($this->ref)) {
            $arr = [
                'type' => $this->type,
            ];

            if (!is_null($this->example)) {
                $arr['example'] = $this->example;
            }

            if (!is_null($this->default)) {
                $arr['default'] = $this->default;
            }

            if (!is_null($this->format)) {
                $arr['format'] = $this->format;
            }

            if (!is_null($this->items)) {
                $arr['items'] = $this->items->toArray();
            }

            return $arr;
        } else {
            return [
                '$ref' => '#/components/schemas/'.$this->ref,
            ];
        }
    }
}