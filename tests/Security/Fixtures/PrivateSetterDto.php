<?php

namespace OV\JsonRPCAPIBundle\Tests\Security\Fixtures;

final class PrivateSetterDto
{
    private string $secret = '';

    public function getSecret(): string
    {
        return $this->secret;
    }

    private function setSecret(string $secret): void
    {
        $this->secret = $secret;
    }
}
