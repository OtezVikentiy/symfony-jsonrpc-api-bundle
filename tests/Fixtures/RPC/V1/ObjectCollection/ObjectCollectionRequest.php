<?php

namespace OV\JsonRPCAPIBundle\RPC\V1\ObjectCollection;

/**
 * A collection held in an object rather than a plain array - the shape a Doctrine ArrayCollection
 * takes. Its setter cannot accept the empty array the empty-collection branch hands over.
 */
final class ObjectCollectionRequest
{
    private Bag $items;

    public function getItems(): Bag
    {
        return $this->items;
    }

    public function setItems(Bag $items): void
    {
        $this->items = $items;
    }

    public function addItem(Item $item): void
    {
        if (!isset($this->items)) {
            $this->items = new Bag();
        }

        $this->items->add($item);
    }
}
