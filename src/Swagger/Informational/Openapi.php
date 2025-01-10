<?php

namespace OV\JsonRPCAPIBundle\Swagger\Informational;

final readonly class Openapi
{
    public function __construct(
        private Info $info,
        private array $servers,
        private array $tags,
        private array $paths,
        private array $components,
    ) {}

    public function toArray(): array
    {
        $servers = [];
        foreach ($this->servers as $server) {
            $servers[] = $server->toArray();
        }

        $paths = [];
        foreach ($this->paths as $path) {
            $paths[$path->getName()] = $path->toArray();
        }

        $schemas = [];
        foreach ($this->components as $component) {
            $schemas[$component->getName()] = $component->toArray();
        }

        return [
            'openapi' => '3.1.1',
            'info' => $this->info->toArray(),
            'servers' => $servers,
            'tags' => $this->tags,
            'paths' => $paths,
            'components' => [
                'schemas' => $schemas
            ],
        ];
    }
}