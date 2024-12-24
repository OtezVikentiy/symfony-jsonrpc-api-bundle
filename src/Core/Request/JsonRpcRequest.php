<?php

namespace OV\JsonRPCAPIBundle\Core\Request;

abstract class JsonRpcRequest
{
    public function toArray(): array
    {
        $reflection = new \ReflectionClass($this);

        $properties = $reflection->getProperties();

        $return = [];
        foreach ($properties as $property) {
            $getterName = $this->createGetter($property->getName());
            $return[$property->getName()] = $this->$getterName();
        }

        return $return;
    }

    private function createGetter(string $propertyName): string
    {
        return 'get' . ucfirst($propertyName);
    }
}