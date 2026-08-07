<?php

namespace OV\JsonRPCAPIBundle\RPC\V1\Handler;

/**
 * The by-position pseudo-field with no default, so hydration must fall through to it rather than to
 * a recorded default.
 */
final class BareParamsRequest
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
