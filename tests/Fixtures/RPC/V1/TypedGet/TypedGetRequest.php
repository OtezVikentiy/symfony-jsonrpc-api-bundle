<?php

namespace OV\JsonRPCAPIBundle\RPC\V1\TypedGet;

/**
 * One field per scalar type, so a single method can show what each transport can and cannot carry.
 */
final class TypedGetRequest
{
    private string $s = '';

    private int $i = 0;

    private bool $b = false;

    private float $f = 0.0;

    public function getS(): string
    {
        return $this->s;
    }

    public function setS(string $s): void
    {
        $this->s = $s;
    }

    public function getI(): int
    {
        return $this->i;
    }

    public function setI(int $i): void
    {
        $this->i = $i;
    }

    public function isB(): bool
    {
        return $this->b;
    }

    public function setB(bool $b): void
    {
        $this->b = $b;
    }

    public function getF(): float
    {
        return $this->f;
    }

    public function setF(float $f): void
    {
        $this->f = $f;
    }
}
