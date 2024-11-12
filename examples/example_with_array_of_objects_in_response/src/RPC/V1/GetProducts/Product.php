<?php

namespace App\RPC\V1\GetProducts;

class Product
{
    private bool $active;
    private int $id;
    private string $title;

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): Product
    {
        $this->active = $active;

        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): Product
    {
        $this->id = $id;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): Product
    {
        $this->title = $title;

        return $this;
    }
}