<?php

declare(strict_types=1);
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\Core\Response;

use DateTimeInterface;
use OV\JsonRPCAPIBundle\Core\JRPCException;
use ReflectionClass;
use ReflectionProperty;
use SplObjectStorage;

final readonly class BaseResponse implements OvResponseInterface, BaseJsonResponseInterface
{
    private const DATE_FORMAT = DATE_ATOM;
    private const GETTER_PREFIXES = ['get', 'is'];

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

    /**
     * @throws JRPCException
     */
    public function toArray(): array
    {
        return [
            'jsonrpc' => $this->jsonrpc,
            'result' => $this->normalizeValue($this->result, new SplObjectStorage()),
            'id' => $this->id,
        ];
    }

    /**
     * @throws JRPCException
     */
    private function normalizeValue(mixed $value, SplObjectStorage $visited): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(self::DATE_FORMAT);
        }

        if (is_object($value)) {
            return $this->objectToArray($value, $visited);
        }

        if (is_array($value)) {
            return array_map(fn(mixed $v) => $this->normalizeValue($v, $visited), $value);
        }

        return $value;
    }

    /**
     * @throws JRPCException
     */
    private function objectToArray(object $object, SplObjectStorage $visited): array
    {
        if ($visited->contains($object)) {
            throw new JRPCException(
                'Cyclic reference detected while serialising the response.',
                JRPCException::INTERNAL_ERROR,
            );
        }

        $visited->attach($object);

        $reflection = new ReflectionClass($object);
        $result = [];

        foreach ($this->collectProperties($reflection) as $name => $property) {
            if (!$property->isInitialized($object)) {
                continue;
            }

            $getter = $this->resolveGetter($reflection, $name);
            if ($getter === null) {
                continue;
            }

            $result[$name] = $this->normalizeValue($object->$getter(), $visited);
        }

        $visited->detach($object);

        return $result;
    }

    /**
     * Collects properties declared anywhere in the class hierarchy, keyed by
     * name. ReflectionClass::getProperties() does not return private
     * properties of parent classes, so each level of the chain is inspected
     * on its own.
     *
     * @return array<string, ReflectionProperty>
     */
    private function collectProperties(ReflectionClass $reflection): array
    {
        $properties = [];

        while ($reflection !== false) {
            foreach ($reflection->getProperties() as $property) {
                $name = $property->getName();
                if (!array_key_exists($name, $properties)) {
                    $properties[$name] = $property;
                }
            }

            $reflection = $reflection->getParentClass();
        }

        return $properties;
    }

    /**
     * Finds the property's getter by exact name, trying `getX` then `isX`
     * then the bare accessor `x` (needed for a boolean property such as
     * `$isActive` whose getter is `isActive()`).
     */
    private function resolveGetter(ReflectionClass $reflection, string $propertyName): ?string
    {
        $candidates = [];
        foreach (self::GETTER_PREFIXES as $prefix) {
            $candidates[] = $prefix . ucfirst($propertyName);
        }
        $candidates[] = $propertyName;

        foreach ($candidates as $candidate) {
            $resolved = $this->resolveMethod($reflection, $candidate);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function resolveMethod(ReflectionClass $reflection, string $methodName): ?string
    {
        if ($reflection->hasMethod($methodName) && $reflection->getMethod($methodName)->isPublic()) {
            return $reflection->getMethod($methodName)->getName();
        }

        return null;
    }
}
