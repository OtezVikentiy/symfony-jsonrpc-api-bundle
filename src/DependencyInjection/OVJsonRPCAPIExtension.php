<?php

namespace OV\JsonRPCAPIBundle\DependencyInjection;

use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

class OVJsonRPCAPIExtension extends Extension
{
    /**
     * @param array $configs
     * @param ContainerBuilder $container
     * @return void
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(dirname(__DIR__).'/../config')
        );
        $loader->load('services.yaml');
        //$loader->load('routes.yaml');

        $configuration = $this->getConfiguration($configs, $container);

        $config = $this->processConfiguration($configuration, $configs);

//        $definition = $container->getDefinition('pgb.password_generator');
//
//        $definition->setArgument('$numbers', $config['numbers']);
//        $definition->setArgument('$upperCase', $config['upperCase']);
//        $definition->setArgument('$lowerCase', $config['lowerCase']);
//        $definition->setArgument('$specialChars', $config['specialChars']);
//        $definition->setArgument('$length', $config['passwordLength']);

        //if (!empty($config['passContentsInterface'])) {
            //$container->setAlias('pgb.default_pass_contents', $config['passContentsInterface']);
            //$definition->setArgument('$passContents', new Reference($config['passContentsInterface']));
        //}
    }

    public function getAlias(): string
    {
        return 'ov_json_rpc_api';
    }
}