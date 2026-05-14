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

final readonly class JsonRpcLogEntry
{
    public function __construct(
        public Direction $direction,
        public ?string $method,
        public string $body,
        public string $contextId,
        public array $meta = [],
    ) {
    }
}
