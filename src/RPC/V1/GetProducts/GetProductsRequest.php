<?php
/** @noinspection PhpUnused */

/** @noinspection PhpUnused */

namespace OV\JsonRPCAPIBundle\RPC\V1\GetProducts;

class GetProductsRequest
{
    private array $ids;

    public function __construct(array $ids)
    {
        $this->ids = $ids;
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function setIds(array $ids): void
    {
        $this->ids = $ids;
    }
}