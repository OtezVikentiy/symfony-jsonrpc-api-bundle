<?php

namespace OV\JsonRPCAPIBundle\RPC\V1\Nested\Multiply;

final class MultiplyResponse
{
    private int $result;

    public function __construct(int $result)
    {
        $this->result = $result;
    }

    public function getResult(): int
    {
        return $this->result;
    }

    public function setResult(int $result): void
    {
        $this->result = $result;
    }
}
