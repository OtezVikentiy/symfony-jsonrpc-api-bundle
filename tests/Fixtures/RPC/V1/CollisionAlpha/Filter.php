<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\RPC\V1\CollisionAlpha;

final class Filter
{
    public function __construct(
        private readonly string $alphaField,
    ) {
    }

    public function getAlphaField(): string
    {
        return $this->alphaField;
    }
}
