<?php
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\Core\Response;

use ReflectionClass;

class BaseResponse implements OvResponseInterface, BaseJsonResponseInterface
{
    public function __construct(
        private readonly mixed $result,
        private readonly ?string $id = null,
        private readonly string $jsonrpc = '2.0'
    ) {
    }

    public function getJsonrpc(): string
    {
        return $this->jsonrpc;
    }

    public function getResult(): mixed
    {
        $ref = new ReflectionClass($this->result::class);
        $props = $ref->getProperties();
        if (count($props) === 1) {
            $methods = $ref->getMethods();
            foreach ($methods as $method) {
                if (str_contains($method->name, 'get')) {
                    return $this->result->{$method->name}();
                }
            }
        }

        return $this->result;
    }

    public function getId(): ?string
    {
        return $this->id;
    }
}