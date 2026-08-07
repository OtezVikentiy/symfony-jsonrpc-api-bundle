<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Swagger\Informational;

/**
 * @internal
 */
final readonly class License
{
    public function __construct(
        private string $name = '',
        private string $url = '',
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}