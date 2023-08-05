<?php

namespace OV\JsonRPCAPIBundle;

use OV\JsonRPCAPIBundle\DependencyInjection\JsonRPCAPICompilerPassBuilder;
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
     * @return void
     */
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(JsonRPCAPICompilerPassBuilder::build($container));
    }
}