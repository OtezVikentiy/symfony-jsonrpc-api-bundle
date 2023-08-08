<?php

namespace OV\JsonRPCAPIBundle\DependencyInjection;

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class JsonRPCAPICompilerPassBuilder
{
    /**
     * @param ContainerBuilder $containerBuilder
     * @return JsonRPCAPICompilerPass
     */
    public static function build(ContainerBuilder $containerBuilder): JsonRPCAPICompilerPass
    {
        return new JsonRPCAPICompilerPass(new AnnotationReader(), new CamelCaseToSnakeCaseNameConverter());
    }
}