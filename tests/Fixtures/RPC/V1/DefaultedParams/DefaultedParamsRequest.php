<?php

namespace OV\JsonRPCAPIBundle\RPC\V1\DefaultedParams;

/**
 * The by-position pseudo-field written the way anyone would write it - with a default, so the DTO
 * is usable before hydration. That default used to win over the payload itself.
 */
final class DefaultedParamsRequest
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
