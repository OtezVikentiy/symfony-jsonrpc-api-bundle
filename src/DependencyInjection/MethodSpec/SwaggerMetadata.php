<?php

namespace OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;

readonly class SwaggerMetadata
{
    public function __construct(
        private string $summary,
        private string $description,
        private bool $ignoreInSwagger,
        private ?array $tags = null,
        private ?string $group = null,
    ) {
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isIgnoreInSwagger(): bool
    {
        return $this->ignoreInSwagger;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }
}
