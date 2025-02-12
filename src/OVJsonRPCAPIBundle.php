<?php
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle;

use OV\JsonRPCAPIBundle\DependencyInjection\CompilerPassBuilder;
use OV\JsonRPCAPIBundle\DependencyInjection\OVJsonRPCAPIExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/** @noinspection PhpUnused */
final class OVJsonRPCAPIBundle extends AbstractBundle
{
    /** @noinspection PhpUnused */
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new OVJsonRPCAPIExtension();
    }

    /** @noinspection PhpUnused */
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(CompilerPassBuilder::build($container));
    }
}