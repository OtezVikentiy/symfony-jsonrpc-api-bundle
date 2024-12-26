<?php
/** @noinspection PhpUnused */

/** @noinspection PhpUnused */

/** @noinspection PhpUnused */

/** @noinspection PhpUnused */

namespace OV\JsonRPCAPIBundle\RPC\V1\GetProducts;

class GetProductsResponse
{
    private bool $success;
    private array $products;

    public function __construct(bool $success = true)
    {
        $this->success = $success;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    public function getProducts(): array
    {
        return $this->products;
    }

    public function setProducts(array $products): void
    {
        $this->products = $products;
    }

    public function addProduct(Product $product): void
    {
        $this->products[] = $product;
    }
}