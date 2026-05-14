<?php
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\Core\Logging;

final class UuidContextIdGenerator implements ContextIdGeneratorInterface
{
    /**
     * @throws \Random\RandomException when the OS CSPRNG is unavailable (extremely rare runtime failure).
     */
    public function generate(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant 10x

        return vsprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            str_split(bin2hex($data), 4),
        );
    }
}
