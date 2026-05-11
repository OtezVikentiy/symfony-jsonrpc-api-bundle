<?php

namespace OV\JsonRPCAPIBundle\Tests\Security\Fixtures;

final class NestedDto
{
    private ?NestedDto $child = null;

    public function getChild(): ?NestedDto
    {
        return $this->child;
    }

    public function setChild(NestedDto $child): void
    {
        $this->child = $child;
    }
}
