<?php
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

final class CompilerPassBuilder
{
    /** @noinspection PhpUnusedParameterInspection */
    public static function build(ContainerBuilder $containerBuilder): CompilerPass
    {
        return new CompilerPass(new CamelCaseToSnakeCaseNameConverter());
    }
}