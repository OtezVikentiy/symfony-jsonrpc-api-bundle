<?php

namespace App\RPC\V1\GetProducts;

class GetProductsResponse
{
    private bool $success;
    private string $title;

    public function __construct(string $title, bool $success = true)
    {
        $this->success = $success;
        $this->title = $title;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }
}