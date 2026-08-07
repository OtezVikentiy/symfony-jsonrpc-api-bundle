<?php

namespace OV\JsonRPCAPIBundle\RPC\V1\Handler;

use InvalidArgumentException;

/**
 * A collection whose element type is a scalar, so the adder is called with the raw value and can
 * refuse it itself.
 */
final class ScalarCollectionRequest
{
    private array $codes = [];

    public function getCodes(): array
    {
        return $this->codes;
    }

    public function setCodes(array $codes): void
    {
        $this->codes = $codes;
    }

    public function addCode(string $code): void
    {
        if ($code === 'refuse') {
            throw new InvalidArgumentException('this code is not allowed');
        }

        $this->codes[] = $code;
    }
}
