<?php

namespace OV\JsonRPCAPIBundle\RPC\V1\Nested\Multiply;

final class MultiplyRequest
{
    private array $params;

    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }
}
