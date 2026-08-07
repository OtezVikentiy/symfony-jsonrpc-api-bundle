<?php

namespace OV\JsonRPCAPIBundle\Tests\DependencyInjection;

use OV\JsonRPCAPIBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testGetConfigTreeBuilderReturnsTreeBuilder(): void
    {
        $configuration = new Configuration();
        $treeBuilder = $configuration->getConfigTreeBuilder();

        $this->assertInstanceOf(TreeBuilder::class, $treeBuilder);
    }

    public function testTreeRootNameIsCorrect(): void
    {
        $configuration = new Configuration();
        $treeBuilder = $configuration->getConfigTreeBuilder();

        $this->assertEquals('ov_json_rpc_api', $treeBuilder->buildTree()->getName());
    }

    public function testProcessEmptyConfiguration(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $config = $processor->processConfiguration($configuration, []);

        $this->assertArrayHasKey('access_control_allow_origin_list', $config);
        $this->assertArrayHasKey('swagger', $config);
    }

    public function testProcessWithAccessControlOrigins(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $config = $processor->processConfiguration($configuration, [
            [
                'access_control_allow_origin_list' => ['https://example.com', 'https://app.example.com'],
            ],
        ]);

        $this->assertEquals(['https://example.com', 'https://app.example.com'], $config['access_control_allow_origin_list']);
    }

    /**
     * The wildcard is checked before any named origin, so it wins outright: such a list reads as a
     * whitelist and behaves as its opposite, and nothing about the running application shows the
     * difference. Compilation is the only moment this is cheap to notice.
     */
    public function testWildcardMixedWithNamedOriginsIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [
            ['access_control_allow_origin_list' => ['https://app.example.com', '*']],
        ]);
    }

    public function testWildcardOnItsOwnIsStillAllowed(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [
            ['access_control_allow_origin_list' => ['*']],
        ]);

        $this->assertEquals(['*'], $config['access_control_allow_origin_list']);
    }

    public function testCorsAllowedHeadersDefaultsToContentTypeOnly(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $config = $processor->processConfiguration($configuration, []);

        $this->assertSame(['Content-Type'], $config['cors_allowed_headers']);
    }

    public function testCorsAllowedHeadersCanBeOverridden(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $config = $processor->processConfiguration($configuration, [
            ['cors_allowed_headers' => ['Content-Type', 'X-AUTH-TOKEN']],
        ]);

        $this->assertSame(['Content-Type', 'X-AUTH-TOKEN'], $config['cors_allowed_headers']);
    }

    public function testProcessWithSwaggerConfig(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $config = $processor->processConfiguration($configuration, [
            [
                'swagger' => [
                    'v1' => [
                        'api_version' => '1',
                        'base_path' => 'https://api.example.com',
                        'test_path' => 'https://test-api.example.com',
                        'auth_token_name' => 'X-AUTH-TOKEN',
                        'auth_token_test_value' => 'test_token',
                        'info' => [
                            'title' => 'My API',
                            'description' => 'My API description',
                            'terms_of_service_url' => 'https://example.com/tos',
                            'contact' => [
                                'name' => 'Support',
                                'url' => 'https://example.com',
                                'email' => 'support@example.com',
                            ],
                            'license' => 'MIT',
                            'licenseUrl' => 'https://opensource.org/licenses/MIT',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertArrayHasKey('v1', $config['swagger']);
        $v1 = $config['swagger']['v1'];
        $this->assertEquals('1', $v1['api_version']);
        $this->assertEquals('https://api.example.com', $v1['base_path']);
        $this->assertEquals('My API', $v1['info']['title']);
        $this->assertEquals('Support', $v1['info']['contact']['name']);
    }

    public function testSwaggerDefaultValues(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $config = $processor->processConfiguration($configuration, [
            [
                'swagger' => [
                    'v1' => [
                        'base_path' => 'https://api.example.com',
                        'auth_token_name' => 'token',
                        'auth_token_test_value' => 'val',
                        'info' => [],
                    ],
                ],
            ],
        ]);

        $v1 = $config['swagger']['v1'];
        $this->assertEquals('1', $v1['api_version']);
        $this->assertNull($v1['base_path_description']);
        $this->assertNull($v1['test_path']);
        $this->assertNull($v1['test_path_description']);
        $this->assertEquals([], $v1['base_path_variables']);
        $this->assertEquals([], $v1['test_path_variables']);
        $this->assertEquals('title', $v1['info']['title']);
        $this->assertEquals('description', $v1['info']['description']);
    }

    public function testStrictNotificationsDefaultTrue(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $config = $processor->processConfiguration($configuration, []);

        $this->assertArrayHasKey('strict_notifications', $config);
        $this->assertTrue($config['strict_notifications']);
    }

    public function testStrictNotificationsCanBeDisabled(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $config = $processor->processConfiguration($configuration, [
            ['strict_notifications' => false],
        ]);

        $this->assertFalse($config['strict_notifications']);
    }

    public function testSecurityDefaults(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $config = $processor->processConfiguration($configuration, []);

        $this->assertFalse($config['expose_internal_errors']);
        $this->assertArrayNotHasKey('cors_strict', $config);
        $this->assertSame(1048576, $config['max_payload_bytes']);
        $this->assertSame(64, $config['max_json_depth']);
        $this->assertSame(50, $config['max_batch_size']);
        $this->assertSame(10, $config['max_dto_depth']);
        $this->assertSame(1000, $config['max_array_param_size']);
    }

    public function testLoggingOverrideServicesDefaultToNull(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $config = $processor->processConfiguration($configuration, []);

        $this->assertNull($config['logging']['logger_service']);
        $this->assertNull($config['logging']['call_logger_service']);
    }

    public function testLoggingOverrideServicesAcceptCustomIds(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $config = $processor->processConfiguration($configuration, [
            [
                'logging' => [
                    'logger_service' => 'app.custom_psr3_logger',
                    'call_logger_service' => 'app.custom_call_logger',
                ],
            ],
        ]);

        $this->assertSame('app.custom_psr3_logger', $config['logging']['logger_service']);
        $this->assertSame('app.custom_call_logger', $config['logging']['call_logger_service']);
    }

    public function testSecurityOverrides(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $config = $processor->processConfiguration($configuration, [
            [
                'expose_internal_errors' => true,
                'max_payload_bytes' => 2048,
                'max_json_depth' => 32,
                'max_batch_size' => 5,
                'max_dto_depth' => 3,
                'max_array_param_size' => 100,
            ],
        ]);

        $this->assertTrue($config['expose_internal_errors']);
        $this->assertSame(2048, $config['max_payload_bytes']);
        $this->assertSame(32, $config['max_json_depth']);
        $this->assertSame(5, $config['max_batch_size']);
        $this->assertSame(3, $config['max_dto_depth']);
        $this->assertSame(100, $config['max_array_param_size']);
    }

    public function testLoggingMaskingKeyPatternsDefaultToANonEmptySecretCoveringSet(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $config = $processor->processConfiguration($configuration, []);

        $patterns = $config['logging']['masking']['key_patterns'];
        $this->assertNotEmpty($patterns, 'flipping logging.enabled=true must not log secrets by default');

        // Every default pattern must itself be a valid, usable regex.
        foreach ($patterns as $pattern) {
            $this->assertNotFalse(@preg_match($pattern, ''), sprintf('default pattern "%s" must be a valid regex', $pattern));
        }

        $this->assertMatchesRegularExpression($patterns[array_search('~password~i', $patterns, true)], 'user_password');
    }

    /**
     * Aligns with the canonical set from ~/engineering-playbook/PLAYBOOK.md §7.3
     * (SecretRedactingProcessor: password|api_key|token|jwt|secret|cookie|cert).
     */
    public function testLoggingMaskingKeyPatternsCoverPlaybookCanonicalSet(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $config = $processor->processConfiguration($configuration, []);

        $patterns = $config['logging']['masking']['key_patterns'];
        $keysExpectedToMatch = [
            'password' => 'user_password',
            'api_key' => 'api_key',
            'token' => 'access_token',
            'jwt' => 'jwt',
            'secret' => 'client_secret',
            'cookie' => 'cookie',
            'cert' => 'client_cert',
        ];

        foreach ($keysExpectedToMatch as $label => $key) {
            $matched = false;
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $key) === 1) {
                    $matched = true;
                    break;
                }
            }
            $this->assertTrue($matched, sprintf('playbook canonical field "%s" (example key "%s") must be covered by a default pattern', $label, $key));
        }
    }

    /**
     * `^cookie$` and `^pwd$` only match a key literally equal to "cookie"/"pwd" — real payloads carry
     * session cookies and passwords under compound names like `session_cookie` or `user_pwd`. Anchoring
     * those two (while every other default pattern is unanchored) left exactly those field names leaking
     * unmasked. Every pattern here must be unanchored so compound field names are still covered.
     */
    public function testLoggingMaskingKeyPatternsMatchCompoundFieldNamesUnanchored(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $config = $processor->processConfiguration($configuration, []);

        $patterns = $config['logging']['masking']['key_patterns'];
        $keysThatMustMatch = [
            'session_cookie',
            'auth_cookie',
            'user_pwd',
            'pwd_hash',
            'client_cert',
            'refreshToken',
            'userPassword',
        ];

        foreach ($keysThatMustMatch as $key) {
            $matched = false;
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $key) === 1) {
                    $matched = true;
                    break;
                }
            }
            $this->assertTrue($matched, sprintf('compound field name "%s" must be covered by a default pattern', $key));
        }
    }

    public function testLoggingMaxBodyLengthDefaultsToNonZero(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $config = $processor->processConfiguration($configuration, []);

        $this->assertGreaterThan(0, $config['logging']['max_body_length'], 'unbounded logging by default reintroduces the leak this config guards against');
    }

    public function testInvalidMaskingKeyPatternFailsContainerBuild(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $this->expectException(InvalidConfigurationException::class);

        $processor->processConfiguration($configuration, [
            [
                'logging' => [
                    'masking' => [
                        'key_patterns' => ['~^password$~i', 'not a valid regex('],
                    ],
                ],
            ],
        ]);
    }

    public function testValidMaskingKeyPatternsAreAccepted(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $config = $processor->processConfiguration($configuration, [
            [
                'logging' => [
                    'masking' => [
                        'key_patterns' => ['~^custom_secret$~i'],
                    ],
                ],
            ],
        ]);

        $this->assertSame(['~^custom_secret$~i'], $config['logging']['masking']['key_patterns']);
    }
}
