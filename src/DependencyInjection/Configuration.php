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

final class Configuration implements ConfigurationInterface
{
    /** @noinspection PhpUnused */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('ov_json_rpc_api');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('access_control_allow_origin_list')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('swagger')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('api_version')->defaultValue('1')->end()
                            ->scalarNode('base_path')->end()
                            ->scalarNode('base_path_description')->defaultNull()->end()
                            ->scalarNode('test_path')->defaultNull()->end()
                            ->scalarNode('test_path_description')->defaultNull()->end()
                            ->arrayNode('base_path_variables')
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('name')->end()
                                        ->scalarNode('value')->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('test_path_variables')
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('name')->end()
                                        ->scalarNode('value')->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->scalarNode('auth_token_name')->end()
                            ->scalarNode('auth_token_test_value')->end()
                            ->arrayNode('info')
                                ->children()
                                    ->scalarNode('title')->defaultValue('title')->end()
                                    ->scalarNode('description')->defaultValue('description')->end()
                                    ->scalarNode('terms_of_service_url')->defaultValue('terms_of_service_url')->end()
                                    ->arrayNode('contact')
                                        ->children()
                                            ->scalarNode('name')->defaultValue('name')->end()
                                            ->scalarNode('url')->defaultValue('url')->end()
                                            ->scalarNode('email')->defaultValue('email')->end()
                                        ->end()
                                    ->end()
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