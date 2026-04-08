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
use ReflectionException;

final readonly class BaseResponse implements OvResponseInterface, BaseJsonResponseInterface
{
    public function __construct(
        private mixed $result,
        private mixed $id = null,
        private string $jsonrpc = '2.0'
    ) {
    }

    /** @noinspection PhpUnused */
    public function getJsonrpc(): string
    {
        return $this->jsonrpc;
    }

    /** @noinspection PhpUnused */
    public function getResult(): mixed
    {
        return $this->result;
    }

    /** @noinspection PhpUnused */
    public function getId(): mixed
    {
        return $this->id;
    }

    public function toArray(): array
    {
        return [
            'jsonrpc' => $this->jsonrpc,
            'result' => $this->normalizeValue($this->result),
            'id' => $this->id,
        ];
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_object($value)) {
            return $this->objectToArray($value);
        }

        if (is_array($value)) {
            return array_map(fn(mixed $v) => $this->normalizeValue($v), $value);
        }

        return $value;
    }

    private function objectToArray(object $object): array
    {
        $result = [];
        $reflection = new ReflectionClass($object);

        foreach ($reflection->getProperties() as $property) {
            if (!$property->isInitialized($object)) {
                continue;
            }

            $name = $property->getName();
            $value = $property->getValue($object);
            $result[$name] = $this->normalizeValue($value);
        }

        return $result;
    }
}