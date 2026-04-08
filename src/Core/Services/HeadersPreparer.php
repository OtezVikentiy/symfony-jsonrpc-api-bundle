<?php

namespace OV\JsonRPCAPIBundle\Core\Services;

final readonly class HeadersPreparer
{
    private array $cachedHeaders;

    public function __construct(
        private array $accessControlAllowOriginList,
    ) {
        $this->cachedHeaders = ['Access-Control-Allow-Origin' => implode(', ', $this->accessControlAllowOriginList)];
    }

    public function prepareHeaders(): array
    {
        return $this->cachedHeaders;
    }
}
