<?php

declare(strict_types=1);
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\DependencyInjection;

use LogicException;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * A developer flipping logging.enabled to true should not have to discover, from the documentation,
     * that key masking needs a pattern list before secrets stop appearing in the log. These cover the
     * field names most commonly used for credentials across typical JSON-RPC request/response payloads.
     *
     * None of these are anchored to an exact key name: an anchored `^cookie$` misses `session_cookie` /
     * `auth_cookie`, and an anchored `^pwd$` misses `user_pwd` / `pwd_hash` — real field names credentials
     * commonly hide behind. The cost of over-masking an unrelated field (e.g. `cookieConsent`) is a
     * slightly less readable log line; the cost of under-masking is a credential in the log. Not symmetric.
     *
     * @var list<string>
     */
    private const DEFAULT_MASKING_KEY_PATTERNS = [
        '~password~i',
        '~passwd~i',
        '~pwd~i',
        '~secret~i',
        '~token~i',
        '~jwt~i',
        '~api[_-]?key~i',
        '~access[_-]?key~i',
        '~private[_-]?key~i',
        '~authorization~i',
        '~credential~i',
        '~session[_-]?id~i',
        '~cookie~i',
        '~card[_-]?number~i',
        '~cvv~i',
        '~cvc~i',
        '~ssn~i',
        '~cert~i',
    ];

    /**
     * Same reasoning as DEFAULT_MASKING_KEY_PATTERNS: logging.enabled=true must not silently log full
     * bodies by default. This bounds a single log line to a size that still carries useful context.
     */
    private const DEFAULT_LOGGING_MAX_BODY_LENGTH = 8192;

    /** @noinspection PhpUnused */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('ov_json_rpc_api');
        $rootNode = $treeBuilder->getRootNode();

        if (!$rootNode instanceof ArrayNodeDefinition) {
            throw new LogicException('Expected the ov_json_rpc_api root node to be an array node.');
        }

        $rootNode
            ->children()
                ->booleanNode('strict_notifications')->defaultTrue()->end()
                ->booleanNode('allow_extra_fields')->defaultFalse()->end()
                ->booleanNode('expose_internal_errors')->defaultFalse()->end()
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
                        ->integerNode('max_body_length')->min(0)->defaultValue(self::DEFAULT_LOGGING_MAX_BODY_LENGTH)->end()
                        ->booleanNode('skip_plain_responses')->defaultTrue()->end()
                        ->scalarNode('logger_service')->defaultNull()->end()
                        ->scalarNode('call_logger_service')->defaultNull()->end()
                        ->arrayNode('masking')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('placeholder')->defaultValue('***')->end()
                                ->arrayNode('key_patterns')
                                    ->scalarPrototype()->end()
                                    ->defaultValue(self::DEFAULT_MASKING_KEY_PATTERNS)
                                    ->validate()
                                        ->ifTrue(static fn (array $patterns): bool => self::containsInvalidMaskingPattern($patterns))
                                        ->thenInvalid('ov_json_rpc_api: logging.masking.key_patterns contains an invalid regular expression: %s')
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('access_control_allow_origin_list')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('cors_allowed_headers')
                    ->prototype('scalar')->end()
                    ->defaultValue(['Content-Type'])
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

    /**
     * SensitiveDataMasker skips an invalid pattern at runtime with a single warning log line and then
     * keeps masking with whatever patterns remain — a typo in this list must not silently downgrade to
     * "no masking for that pattern" in production, so the container build itself must refuse to start.
     *
     * @param array<int, mixed> $patterns
     */
    private static function containsInvalidMaskingPattern(array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (!is_string($pattern) || @preg_match($pattern, '') === false) {
                return true;
            }
        }

        return false;
    }
}
