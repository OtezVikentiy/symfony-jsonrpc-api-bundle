<?php

namespace OV\JsonRPCAPIBundle\Swagger;

class License
{
    public function __construct(
        private readonly string $name = '',
        private readonly string $url = '',
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