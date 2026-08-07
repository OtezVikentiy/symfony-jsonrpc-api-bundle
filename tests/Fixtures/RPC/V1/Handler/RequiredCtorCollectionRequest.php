<?php

namespace OV\JsonRPCAPIBundle\RPC\V1\Handler;

final class RequiredCtorCollectionRequest
{
    private array $tags = [];

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    public function addTag(RequiredCtorTag $tag): void
    {
        $this->tags[] = $tag;
    }
}
