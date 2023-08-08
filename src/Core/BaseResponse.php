<?php

namespace OV\JsonRPCAPIBundle\Core;

class BaseResponse
{
    public function __construct(
        private readonly mixed $result,
        private readonly ?int $id = null,
        private readonly string $jsonrpc = '2.0'
    ) {
    }

    public function getJsonrpc(): string
    {
        return $this->jsonrpc;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}