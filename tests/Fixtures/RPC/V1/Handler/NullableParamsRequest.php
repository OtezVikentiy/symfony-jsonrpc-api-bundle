<?php

namespace OV\JsonRPCAPIBundle\RPC\V1\Handler;

final class NullableParamsRequest
{
    private ?array $params = null;

    public function getParams(): ?array
    {
        return $this->params;
    }

    public function setParams(?array $params): void
    {
        $this->params = $params;
    }
}
