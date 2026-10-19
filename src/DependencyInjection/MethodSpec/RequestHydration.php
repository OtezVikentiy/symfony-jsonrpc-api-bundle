<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/** @internal Compile the accessor-free hydration contract once, including for hand-built specs. */
final class RequestHydration
{
    public const PROPERTY = 'property';
    public const PROMOTED = 'promoted';
    public const CONSTRUCTOR_OBJECT = 'constructor_object';

    public static function strategy(ReflectionProperty $property): ?string
    {
        if (!$property->isPublic() || $property->isStatic()
            || (PHP_VERSION_ID >= 80400 && $property->isPrivateSet())
            || (PHP_VERSION_ID >= 80400 && $property->hasHooks())
        ) {
            return null;
        }

        $type = $property->getType();
        if ($property->isPromoted()) {
            // Constructors receive decoded values. Nested objects still require setters.
            if (!$type instanceof ReflectionNamedType || !$type->isBuiltin()) {
                return null;
            }
            if (!$property->isReadOnly() && PHP_VERSION_ID >= 80400 && $property->isProtectedSet()) {
                return null;
            }

            return self::PROMOTED;
        }
        if ($property->isReadOnly() || (PHP_VERSION_ID >= 80400 && $property->isProtectedSet())) {
            return null;
        }
        // A constructor may explicitly turn its raw array into this object. Preserve that
        // object if it exists; an unassigned property must still receive the caller's value.
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            foreach ($property->getDeclaringClass()->getConstructor()?->getParameters() ?? [] as $parameter) {
                if ($parameter->getName() === $property->getName()) {
                    return self::CONSTRUCTOR_OBJECT;
                }
            }
        }

        return self::PROPERTY;
    }

    public static function forClass(?string $class): array
    {
        $strategies = [];
        if ($class !== null && class_exists($class)) {
            foreach ((new ReflectionClass($class))->getProperties() as $property) {
                $strategy = self::strategy($property);
                if ($strategy !== null) {
                    $strategies[$property->getName()] = $strategy;
                }
            }
        }

        return $strategies;
    }
}
