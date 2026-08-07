<?php

namespace OV\JsonRPCAPIBundle\RPC\V1\Handler;

use OV\JsonRPCAPIBundle\Core\Request\PartialUpdateRequest;

final class CollectionRequest extends PartialUpdateRequest
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

    public function addTag(Tag $tag): void
    {
        $this->tags[] = $tag;
    }
}
