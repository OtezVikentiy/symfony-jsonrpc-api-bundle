<?php
/** @noinspection PhpUnused */

/** @noinspection PhpUnused */

/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\RPC\V1\Subtract2;

class Subtract2Request
{
    private int $subtrahend;
    private int $minuend;

    public function getSubtrahend(): int
    {
        return $this->subtrahend;
    }

    public function setSubtrahend(int $subtrahend): void
    {
        $this->subtrahend = $subtrahend;
    }

    public function getMinuend(): int
    {
        return $this->minuend;
    }

    public function setMinuend(int $minuend): void
    {
        $this->minuend = $minuend;
    }
}