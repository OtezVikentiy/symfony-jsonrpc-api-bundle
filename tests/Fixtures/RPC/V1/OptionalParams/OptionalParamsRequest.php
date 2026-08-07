<?php

namespace OV\JsonRPCAPIBundle\RPC\V1\OptionalParams;

/**
 * Declares the `params` pseudo-field alongside a named one, and makes it optional. A by-name call
 * that omits it is ordinary and must stay ordinary.
 */
final class OptionalParamsRequest
{
    private array $params = [];

    private string $other;

    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function getOther(): string
    {
        return $this->other;
    }

    public function setOther(string $other): void
    {
        $this->other = $other;
    }
}
