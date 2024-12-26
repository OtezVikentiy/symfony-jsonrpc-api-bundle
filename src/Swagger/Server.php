<?php

namespace OV\JsonRPCAPIBundle\Swagger;

readonly class Server
{
    public function __construct(
        private string $url = '',
        private string $description = '',
    ) {}

    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'description' => $this->description,
        ];
    }
}