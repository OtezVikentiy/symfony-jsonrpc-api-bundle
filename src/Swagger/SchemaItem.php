<?php

namespace OV\JsonRPCAPIBundle\Swagger;

final class SchemaItem
{
    public function __construct(
        private readonly string $type = '',
        private readonly ?int $minItems = null,
        private readonly ?int $maxItems = null,
        private readonly ?string $ref = null,
        private ?array $items = null,
    ) {}

    public function addItem(SchemaItem $item): void
    {
        $this->items[] = $item;
    }

    public function toArray(): array
    {
        $array = [
            'type' => $this->type,
        ];

        if (!is_null($this->minItems)) {
            $array['minItems'] = $this->minItems;
        }

        if (!is_null($this->maxItems)) {
            $array['maxItems'] = $this->maxItems;
        }

        if (!is_null($this->ref)) {
            $array['$ref'] = '#/components/schemas/'.$this->ref;
        }

        if (!is_null($this->items)) {
            $array['items'] = $this->items;
        }

        return $array;
    }
}