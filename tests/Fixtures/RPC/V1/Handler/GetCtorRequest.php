<?php

namespace OV\JsonRPCAPIBundle\RPC\V1\Handler;

/**
 * A required constructor parameter and a nested DTO, both typed - the two places a query-string
 * value reaches a typed target other than a plain top-level setter.
 */
final class GetCtorRequest
{
    private int $id;

    private GetCtorInner $inner;

    public function __construct(int $id)
    {
        $this->id = $id;
        $this->inner = new GetCtorInner();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getInner(): GetCtorInner
    {
        return $this->inner;
    }

    public function setInner(GetCtorInner $inner): void
    {
        $this->inner = $inner;
    }
}
