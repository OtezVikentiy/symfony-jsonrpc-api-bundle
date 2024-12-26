<?php
/** @noinspection PhpUnused */

/** @noinspection PhpUnused */

namespace OV\JsonRPCAPIBundle\RPC\V1\PlainResponse;

use OV\JsonRPCAPIBundle\Core\Request\JsonRpcRequest;

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