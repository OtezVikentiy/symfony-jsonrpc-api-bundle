<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\RPC\V1\Throwing;

final class ThrowingRequest
{
    private array $params = [];

    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }
}
