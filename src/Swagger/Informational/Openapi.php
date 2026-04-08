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
        private ?string $securitySchemeName = null,
        private ?array $securityScheme = null,
    ) {
    }

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

        $result = [
            'openapi' => '3.1.1',
            'info' => $this->info->toArray(),
        ];

        if (!empty($servers)) {
            $result['servers'] = $servers;
        }

        $filteredTags = array_values(array_filter($this->tags, fn($tag) => !empty($tag)));
        if (!empty($filteredTags)) {
            $result['tags'] = $filteredTags;
        }

        if (!empty($paths)) {
            $result['paths'] = $paths;
        }

        $components = [];
        if (!empty($schemas)) {
            $components['schemas'] = $schemas;
        }
        if ($this->securitySchemeName !== null && $this->securityScheme !== null) {
            $components['securitySchemes'] = [
                $this->securitySchemeName => $this->securityScheme,
            ];
        }
        if (!empty($components)) {
            $result['components'] = $components;
        }

        if ($this->securitySchemeName !== null) {
            $result['security'] = [
                [$this->securitySchemeName => []],
            ];
        }

        return $result;
    }
}
