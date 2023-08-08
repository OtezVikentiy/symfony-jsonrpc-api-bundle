<?php

namespace OV\JsonRPCAPIBundle\DependencyInjection;

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class CompilerPassBuilder
{
    /**
     * @param ContainerBuilder $containerBuilder
     * @return CompilerPass
     */
    public static function build(ContainerBuilder $containerBuilder): CompilerPass
    {
        return new CompilerPass(new AnnotationReader(), new CamelCaseToSnakeCaseNameConverter());
    }
}