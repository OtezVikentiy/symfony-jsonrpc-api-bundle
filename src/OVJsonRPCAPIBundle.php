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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use OV\JsonRPCAPIBundle\DependencyInjection\OVJsonRPCAPIExtension;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class OVJsonRPCAPIBundle extends AbstractBundle
{
    /**
     * @return ExtensionInterface|null
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new OVJsonRPCAPIExtension();
    }

    /**
     * @param ContainerBuilder $container
     *
     * @return void
     */
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(CompilerPassBuilder::build($container));
    }
}