<?php

namespace OV\JsonRPCAPIBundle\Swagger;

readonly class License
{
    public function __construct(
        private string $name = '',
        private string $url = '',
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}