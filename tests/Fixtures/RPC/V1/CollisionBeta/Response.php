<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\RPC\V1\CollisionBeta;

final class Response
{
    public function __construct(
        private readonly Filter $filter,
    ) {
    }

    public function getFilter(): Filter
    {
        return $this->filter;
    }
}
