<?php
namespace OV\JsonRPCAPIBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('ov_json_rpc_api');

        /*$treeBuilder->getRootNode()
            ->children()
                ->integerNode('passwordLength')->defaultValue(15)->end()
                ->scalarNode('passContentsInterface')->defaultNull()->info('PassContentsInterface realisation should be passed here')->end()
                ->booleanNode('numbers')->defaultTrue()->end()
                ->booleanNode('upperCase')->defaultTrue()->end()
                ->booleanNode('lowerCase')->defaultTrue()->end()
                ->booleanNode('specialChars')->defaultTrue()->end()
            ->end()
        ;*/

        return $treeBuilder;
    }
}