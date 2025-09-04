<?php

namespace OV\JsonRPCAPIBundle\Core\Request;

use ReflectionClass;

abstract class JsonRpcRequest
{
    public function toArray(): array
    {
        $reflection = new ReflectionClass($this);

        $properties = $reflection->getProperties();

        $return = [];
        foreach ($properties as $property) {
            $getterName = $this->createGetter($property->getName(), $property->getType()->getName());
            $return[$property->getName()] = $this->$getterName();
        }

        return $return;
    }

    private function createGetter(string $propertyName, string $propertyType): string
    {
        if ($propertyType === 'bool' || $propertyType === 'boolean') {
            return 'is' . ucfirst($propertyName);
        }

        return 'get' . ucfirst($propertyName);
    }
}