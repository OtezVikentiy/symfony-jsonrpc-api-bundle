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
                ->booleanNode('strict_notifications')->defaultTrue()->end()
                ->booleanNode('allow_extra_fields')->defaultFalse()->end()
                ->booleanNode('expose_internal_errors')->defaultFalse()->end()
                ->booleanNode('cors_strict')->defaultTrue()->end()
                ->integerNode('max_payload_bytes')->min(1024)->defaultValue(1048576)->end()
                ->integerNode('max_json_depth')->min(1)->max(512)->defaultValue(64)->end()
                ->integerNode('max_batch_size')->min(1)->defaultValue(50)->end()
                ->integerNode('max_dto_depth')->min(1)->defaultValue(10)->end()
                ->integerNode('max_array_param_size')->min(1)->defaultValue(1000)->end()
                ->arrayNode('logging')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('request_level')->defaultValue('info')->end()
                        ->scalarNode('response_level')->defaultValue('info')->end()
                        ->scalarNode('error_response_level')->defaultValue('warning')->end()
                        ->integerNode('max_body_length')->min(0)->defaultValue(0)->end()
                        ->booleanNode('skip_plain_responses')->defaultTrue()->end()
                        ->arrayNode('masking')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('placeholder')->defaultValue('***')->end()
                                ->arrayNode('key_patterns')
                                    ->scalarPrototype()->end()
                                    ->defaultValue([])
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
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