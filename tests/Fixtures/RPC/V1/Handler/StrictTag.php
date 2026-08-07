<?php

namespace OV\JsonRPCAPIBundle\RPC\V1\Handler;

use InvalidArgumentException;

/**
 * Refuses to be built from anything but a known name - the shape a value object takes when it
 * validates in its own constructor.
 */
final class StrictTag
{
    private string $name = '';

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        if ($name === 'refuse') {
            throw new InvalidArgumentException('this name is not allowed');
        }

        $this->name = $name;
    }
}
