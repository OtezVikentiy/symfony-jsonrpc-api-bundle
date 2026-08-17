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
        '~bearer~i',
        '~\bauth\b|auth[_-]~i',
        '~signature~i',
        '~\bsign\b|sign[_-]~i',
        '~hmac~i',
        '~\bpin\b|pin[_-]~i',
        '~\botp\b|otp[_-]~i',
        '~\bsalt\b|salt[_-]~i',
        '~\bhash\b|hash[_-]|[_-]hash~i',
        '~iban~i',
        '~\bpan\b|pan[_-]~i',
    ];

    /**
     * Same reasoning as DEFAULT_MASKING_KEY_PATTERNS: logging.enabled=true must not silently log full
     * bodies by default. This bounds a single log line to a size that still carries useful context.
     */
    private const DEFAULT_LOGGING_MAX_BODY_LENGTH = 8192;

    /**
     * A single uploaded file is bounded independently of max_payload_bytes: the JSON envelope and a
     * file part are different kinds of input, and raising the limit for one of them should not raise
     * it for the other. 10 MiB covers the documents and images an RPC method realistically receives
     * while keeping the default well below a typical PHP upload_max_filesize.
     *
     * Written the way Assert\File takes it, so the default reads as the same thing a user would type.
     */
    private const DEFAULT_MULTIPART_MAX_FILE_BYTES = '10Mi';

    /**
     * Same reasoning as max_batch_size: the number of parts is caller-controlled, so it needs a bound
     * that does not depend on any single part being small.
     */
    private const DEFAULT_MULTIPART_MAX_FILES = 10;

    private const ORIGIN_WILDCARD = '*';

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
                ->arrayNode('multipart')
                    ->addDefaultsIfNotSet()
                    ->children()
                        // Off by default on purpose: an installation that never receives a file gains no
                        // new surface from this feature, and the content-type assertion keeps refusing
                        // everything that is not application/json exactly as it did before.
                        ->booleanNode('enabled')->defaultFalse()->end()
                        // Scalar, not integer: the value is handed to Assert\File::maxSize, which
                        // reads Symfony's own size notation - '10M', '2Mi', '512k'. Refusing the
                        // spelling the rest of Symfony accepts would be a gratuitous difference.
                        ->scalarNode('max_file_bytes')
                            ->defaultValue(self::DEFAULT_MULTIPART_MAX_FILE_BYTES)
                            ->validate()
                                ->ifTrue(static fn (mixed $maxSize): bool => !self::isValidFileSize($maxSize))
                                ->thenInvalid('ov_json_rpc_api: multipart.max_file_bytes must be a positive number of bytes or a size such as "10M" or "2Mi", got %s.')
                            ->end()
                        ->end()
                        ->integerNode('max_files')->min(1)->defaultValue(self::DEFAULT_MULTIPART_MAX_FILES)->end()
                    ->end()
                ->end()
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
                    ->validate()
                        ->ifTrue(static fn (array $origins): bool => self::mixesWildcardWithNamedOrigins($origins))
                        ->thenInvalid('ov_json_rpc_api: access_control_allow_origin_list mixes the "*" wildcard with named origins, which silently allows every origin. List the origins you mean, or use "*" on its own.')
                    ->end()
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
                                // Without this the declared defaults below never apply: a node that
                                // the configuration omits produces no key at all, and the OpenAPI
                                // generator reads `info.title` and friends unguarded. Leaving the
                                // block out - which the schema permits - aborted ov:swagger:generate
                                // with a TypeError instead of generating with the defaults.
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->scalarNode('title')->defaultValue('title')->end()
                                    ->scalarNode('description')->defaultValue('description')->end()
                                    ->scalarNode('terms_of_service_url')->defaultValue('terms_of_service_url')->end()
                                    ->arrayNode('contact')
                                        ->addDefaultsIfNotSet()
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
     * The spellings Assert\File::maxSize accepts, checked here so a typo fails the container build
     * rather than the first request that carries a file. Symfony's own parser is private to the
     * constraint, so this mirrors the pattern it applies rather than calling it - and the constraint
     * is constructed with this value during the same build, which is the real proof.
     */
    private static function isValidFileSize(mixed $maxSize): bool
    {
        if (is_int($maxSize)) {
            return $maxSize > 0;
        }

        if (!is_string($maxSize)) {
            return false;
        }

        return preg_match('/^[1-9]\d*+(k|ki|m|mi|g|gi)?$/i', $maxSize) === 1;
    }

    /**
     * A wildcard sitting next to named origins is almost always a leftover, and it wins: the
     * wildcard is checked first, so `['https://app.example.com', '*']` answers every origin with
     * `Access-Control-Allow-Origin: *`. The list reads as a whitelist and behaves as its opposite,
     * and nothing about the running application shows the difference until someone looks. Refusing
     * to compile is the only moment this is cheap to notice.
     *
     * @param array<int, mixed> $origins
     */
    private static function mixesWildcardWithNamedOrigins(array $origins): bool
    {
        return in_array(self::ORIGIN_WILDCARD, $origins, true) && count($origins) > 1;
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
