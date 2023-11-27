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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('ov_json_rpc_api');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('swagger')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('swagger_version')->defaultValue('1.2')->end()
                            ->scalarNode('api_version')->defaultValue('1')->end()
                            ->arrayNode('info')
                                ->children()
                                    ->scalarNode('title')->defaultValue('title')->end()
                                    ->scalarNode('description')->defaultValue('description')->end()
                                    ->scalarNode('terms_of_service_url')->defaultValue('terms_of_service_url')->end()
                                    ->scalarNode('contact')->defaultValue('contact')->end()
                                    ->scalarNode('license')->defaultValue('license')->end()
                                    ->scalarNode('licenseUrl')->defaultValue('licenseUrl')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}