<?php

namespace OV\JsonRPCAPIBundle\DependencyInjection;

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class JsonRPCAPICompilerPassBuilder
{
    /**
     * @param ContainerBuilder $containerBuilder
     * @return JsonRPCAPICompilerPass
     */
    public static function build(ContainerBuilder $containerBuilder): JsonRPCAPICompilerPass
    {
        return new JsonRPCAPICompilerPass(new AnnotationReader());
    }
}