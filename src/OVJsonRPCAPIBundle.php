<?php

namespace OV\JsonRPCAPIBundle;

use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use OV\JsonRPCAPIBundle\DependencyInjection\OVJsonRPCAPIExtension;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class OVJsonRPCAPIBundle extends AbstractBundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new OVJsonRPCAPIExtension();
    }
}