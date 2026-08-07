<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Core\Request;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Serialization\SerialisesPublicSurface;
use SplObjectStorage;

abstract class JsonRpcRequest
{
    use SerialisesPublicSurface;

    /**
     * @return array<string, mixed>
     *
     * @throws JRPCException
     */
    public function toArray(): array
    {
        return $this->objectToArray($this, new SplObjectStorage());
    }

    /**
     * A nested value carrying its own toArray() decides its own shape - long-standing behaviour of
     * request DTOs, and the reason a child can appear under keys its properties are not named after.
     *
     * Another JsonRpcRequest is excluded on purpose. It inherits toArray(), so delegating to it
     * would restart the walk with an empty visited set, and two DTOs referring to each other would
     * recurse until the stack overflowed - the very failure the shared traversal exists to catch.
     * Such a value is walked inline instead, under the visited set already in flight.
     *
     * @return array<mixed>|null
     */
    private function customArrayRepresentation(object $value): ?array
    {
        if ($value instanceof self || !method_exists($value, 'toArray')) {
            return null;
        }

        $representation = $value->toArray();

        return is_array($representation) ? $representation : null;
    }
}
