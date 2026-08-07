<?php

namespace OV\JsonRPCAPIBundle\RPC\V1\ObjectCollection;

final class Bag
{
    private array $items = [];

    public function add(Item $item): void
    {
        $this->items[] = $item;
    }

    public function all(): array
    {
        return $this->items;
    }
}
