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

namespace OV\JsonRPCAPIBundle\Core\Serialization;

use DateTimeInterface;
use OV\JsonRPCAPIBundle\Core\JRPCException;
use ReflectionClass;
use ReflectionProperty;
use SplObjectStorage;

/**
 * Turns an object graph into arrays, exporting only what the class makes public.
 *
 * Response serialisation and JsonRpcRequest::toArray() both walk arbitrary user DTOs, and both used
 * to do it with their own copy of the logic - which meant the response side could be hardened while
 * the request side kept the original two defects. Reflection reads a private property just as
 * happily as a public one, so a DTO carrying a password hash or an internal token exported it the
 * moment anything called toArray(); and neither side tracked which objects it had already entered,
 * so a graph with a back-reference recursed until the stack overflowed and took the worker with it,
 * leaving no response and no log entry, because a stack overflow is not an exception one can catch.
 *
 * A property is exported when the class exposes it: through a public getter, or by being public
 * itself. The line is visibility, not ceremony. The defect this exists to stop was a *private* field
 * escaping through Reflection, and that stays stopped - a private property with no getter, or with a
 * private one, never leaves. Requiring a getter on top of that would protect nothing and would drop
 * the promoted public properties that are the shortest honest way to write a response DTO.
 *
 * @internal
 */
trait SerialisesPublicSurface
{
    private const DATE_FORMAT = DATE_ATOM;
    private const GETTER_PREFIXES = ['get', 'is'];

    /**
     * Arrays are guarded by depth rather than by identity: PHP arrays are values, so a
     * self-referencing one (`$a['self'] = &$a`) cannot be recognised by SplObjectStorage the way a
     * repeated object can, and it recursed straight into a segmentation fault. The bound doubles as
     * a ceiling on object graphs that are deep without being cyclic.
     */
    private const MAX_SERIALISATION_DEPTH = 64;

    /**
     * @throws JRPCException
     */
    private function normaliseValue(mixed $value, SplObjectStorage $visited, int $depth = 0): mixed
    {
        if ($depth > self::MAX_SERIALISATION_DEPTH) {
            throw new JRPCException(
                'Value nesting is too deep to serialise.',
                JRPCException::INTERNAL_ERROR,
            );
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(self::DATE_FORMAT);
        }

        if (is_object($value)) {
            $custom = $this->customArrayRepresentation($value);

            return $custom ?? $this->objectToArray($value, $visited, $depth);
        }

        if (is_array($value)) {
            $normalised = [];
            foreach ($value as $key => $item) {
                $normalised[$key] = $this->normaliseValue($item, $visited, $depth + 1);
            }

            return $normalised;
        }

        return $value;
    }

    /**
     * Hook for a nested value that knows how to represent itself; null means "walk it normally".
     *
     * Declaring a method of this name on the including class replaces this one. The request side
     * uses that to honour a nested DTO's own toArray(), which has always been part of its contract.
     * The response side deliberately does not: what a response exposes is decided by its getters,
     * and letting an arbitrary nested object rewrite that would reopen the hole getter-only
     * serialisation was introduced to close.
     *
     * @return array<mixed>|null
     */
    private function customArrayRepresentation(object $value): ?array
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JRPCException
     */
    private function objectToArray(object $object, SplObjectStorage $visited, int $depth = 0): array
    {
        if ($visited->contains($object)) {
            throw new JRPCException(
                'Cyclic reference detected while serialising the payload.',
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
                // No getter, but the property itself is public - which is the author declaring it
                // part of the object's surface, in as many words. Promoted constructor properties
                // make that the shortest way to write a response DTO, and dropping them protects
                // nobody: the leak this serialiser exists to stop was a *private* field escaping
                // through Reflection, and a private field with no getter still does not leave.
                if (!$property->isPublic()) {
                    continue;
                }

                $result[$name] = $this->normaliseValue($property->getValue($object), $visited, $depth + 1);

                continue;
            }

            $result[$name] = $this->normaliseValue($object->$getter(), $visited, $depth + 1);
        }

        $visited->detach($object);

        return $result;
    }

    /**
     * Collects properties declared anywhere in the class hierarchy, keyed by name.
     * ReflectionClass::getProperties() does not return private properties of parent classes, so each
     * level of the chain is inspected on its own.
     *
     * @param ReflectionClass<object> $reflection
     *
     * @return array<string, ReflectionProperty>
     */
    private function collectProperties(ReflectionClass $reflection): array
    {
        $properties = [];
        $current = $reflection;

        while ($current !== false) {
            foreach ($current->getProperties() as $property) {
                $name = $property->getName();
                if (!array_key_exists($name, $properties)) {
                    $properties[$name] = $property;
                }
            }

            $current = $current->getParentClass();
        }

        return $properties;
    }

    /**
     * Finds the property's getter by exact name, trying `getX` then `isX` then the bare accessor `x`.
     *
     * @param ReflectionClass<object> $reflection
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

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function resolveMethod(ReflectionClass $reflection, string $methodName): ?string
    {
        if ($reflection->hasMethod($methodName) && $reflection->getMethod($methodName)->isPublic()) {
            return $reflection->getMethod($methodName)->getName();
        }

        return null;
    }
}
