<?php

namespace OV\JsonRPCAPIBundle\Core\Request;

abstract class JsonRpcRequest
{
    public function toArray(): array
    {
        return $this->objectToArray($this);
    }

    private function processValue(mixed $value): mixed
    {
        if (is_object($value)) {
            if ($value instanceof \DateTime) {
                return $value;
            }
            
            if (method_exists($value, 'toArray')) {
                return $value->toArray();
            }

            return $this->objectToArray($value);
        }

        if (is_array($value)) {
            $processedArray = [];
            foreach ($value as $key => $item) {
                $processedArray[$key] = $this->processValue($item);
            }
            return $processedArray;
        }

        return $value;
    }

    private function objectToArray(object $object): array
    {
        $reflection = new \ReflectionClass($object);
        $properties = $reflection->getProperties();
        $result = [];

        foreach ($properties as $property) {
            $property->setAccessible(true);
            $propertyName = $property->getName();
            $type = $property->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;

            $getterName = $this->createGetter($propertyName, $typeName);

            if (method_exists($object, $getterName) && $property->isInitialized($object)) {
                $value = $object->$getterName();
            } elseif ($property->isInitialized($object)) {
                $value = $property->getValue($object);
            } else {
                continue;
            }

            $result[$propertyName] = $this->processValue($value);
        }

        return $result;
    }

    private function createGetter(string $propertyName, ?string $propertyType): string
    {
        if ($propertyType === 'bool' || $propertyType === 'boolean') {
            return 'is' . ucfirst($propertyName);
        }
        return 'get' . ucfirst($propertyName);
    }
}