<?php

namespace OV\JsonRPCAPIBundle\Tests\Security\Fixtures;

final class ScalarSetterDto
{
    private int $amount;

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
    }
}
