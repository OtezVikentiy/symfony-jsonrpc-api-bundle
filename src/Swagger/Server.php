<?php

namespace OV\JsonRPCAPIBundle\Swagger;

class Server
{
    public function __construct(
        private readonly string $url = '',
        private readonly string $description = '',
    ) {}

    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'description' => $this->description,
        ];
    }
}