<?php

namespace OV\JsonRPCAPIBundle\RPC\V1\Handler;

final class GetCtorInner
{
    private int $depth = 0;

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function setDepth(int $depth): void
    {
        $this->depth = $depth;
    }
}
