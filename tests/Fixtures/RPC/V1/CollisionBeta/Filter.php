<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\RPC\V1\CollisionBeta;

final class Filter
{
    public function __construct(
        private readonly int $betaField,
    ) {
    }

    public function getBetaField(): int
    {
        return $this->betaField;
    }
}
