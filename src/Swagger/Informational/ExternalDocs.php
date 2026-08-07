<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Swagger\Informational;

/**
 * @internal
 */
final readonly class ExternalDocs
{
    public function __construct(
        private string $description = '',
        private string $url = '',
    ) {
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}