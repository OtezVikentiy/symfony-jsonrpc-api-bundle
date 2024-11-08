<?php

namespace OV\JsonRPCAPIBundle\Core;

abstract class JsonRpcRequest
{
    public function toArray(): array
    {
        $reflection = new \ReflectionClass($this);
        
        $properties = $reflection->getProperties();
        
        $return = [];
        foreach ($properties as $property) {
            $return[$property->getName()] = $this->{$property->getName()};
        }
        
        return $return;
    }
}