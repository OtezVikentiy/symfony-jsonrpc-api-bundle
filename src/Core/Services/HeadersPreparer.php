<?php

namespace OV\JsonRPCAPIBundle\Core\Services;

final readonly class HeadersPreparer
{
    public function __construct(
        private array $accessControlAllowOriginList,
    ) {
    }

    public function prepareHeaders(): array
    {
        return ['Access-Control-Allow-Origin' => implode(', ', $this->accessControlAllowOriginList)];
    }
}