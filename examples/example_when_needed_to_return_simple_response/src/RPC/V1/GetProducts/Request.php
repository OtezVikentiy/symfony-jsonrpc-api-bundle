<?php

namespace App\RPC\V1\GetProducts;

use OV\JsonRPCAPIBundle\Core\JsonRpcRequest;

class Request extends JsonRpcRequest
{
    private int $id;

    public function __construct(int $id)
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }
}