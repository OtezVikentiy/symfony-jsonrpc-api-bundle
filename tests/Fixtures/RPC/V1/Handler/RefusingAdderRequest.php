<?php

namespace OV\JsonRPCAPIBundle\RPC\V1\Handler;

use InvalidArgumentException;

/**
 * The element builds fine; the collection refuses to accept it. A quota, a duplicate check - the
 * adder is where a collection enforces its own rules.
 */
final class RefusingAdderRequest
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
        if ($tag->getName() === 'refuse') {
            throw new InvalidArgumentException('this collection will not take that');
        }

        $this->tags[] = $tag;
    }
}
