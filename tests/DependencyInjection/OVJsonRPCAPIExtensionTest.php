<?php

namespace OV\JsonRPCAPIBundle\Tests\DependencyInjection;

use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcCallLogger;
use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcCallLoggerInterface;
use OV\JsonRPCAPIBundle\Core\Logging\NullJsonRpcCallLogger;
use OV\JsonRPCAPIBundle\DependencyInjection\OVJsonRPCAPIExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class OVJsonRPCAPIExtensionTest extends TestCase
{
    public function testAliasIsExpected(): void
    {
        $extension = new OVJsonRPCAPIExtension();

        $this->assertSame('ov_json_rpc_api', $extension->getAlias());
    }

    public function testContainerIsNotAliasedForConsumerAutowiring(): void
    {
        $container = new ContainerBuilder();
        $extension = new OVJsonRPCAPIExtension();

        $extension->load([], $container);

        $this->assertFalse(
            $container->hasAlias(Container::class),
            'The bundle must not alias the DI Container to @service_container: any consumer service '
                . 'autowiring Container would receive the whole application container instead of the '
                . 'narrow ServiceLocator this bundle needs internally.',
        );
    }

    public function testLoadRegistersDefaultParameters(): void
    {
        $container = new ContainerBuilder();
        $extension = new OVJsonRPCAPIExtension();

        $extension->load([], $container);

        $this->assertTrue($container->getParameter('ov_json_rpc_api.strict_notifications'));
        $this->assertFalse($container->getParameter('ov_json_rpc_api.allow_extra_fields'));
        $this->assertFalse($container->getParameter('ov_json_rpc_api.expose_internal_errors'));
        $this->assertFalse($container->hasParameter('ov_json_rpc_api.cors_strict'));
        $this->assertSame(['Content-Type'], $container->getParameter('ov_json_rpc_api.cors_allowed_headers'));
        $this->assertSame(1048576, $container->getParameter('ov_json_rpc_api.max_payload_bytes'));
        $this->assertSame(64, $container->getParameter('ov_json_rpc_api.max_json_depth'));
        $this->assertSame(50, $container->getParameter('ov_json_rpc_api.max_batch_size'));
        $this->assertSame(10, $container->getParameter('ov_json_rpc_api.max_dto_depth'));
        $this->assertSame(1000, $container->getParameter('ov_json_rpc_api.max_array_param_size'));
        $this->assertFalse($container->getParameter('ov_json_rpc_api.multipart.enabled'));
        $this->assertSame('10Mi', $container->getParameter('ov_json_rpc_api.multipart.max_file_bytes'));
        $this->assertSame(10, $container->getParameter('ov_json_rpc_api.multipart.max_files'));
    }

    public function testLoadAppliesUserOverrides(): void
    {
        $container = new ContainerBuilder();
        $extension = new OVJsonRPCAPIExtension();

        $extension->load([
            [
                'strict_notifications' => false,
                'max_batch_size' => 10,
                'access_control_allow_origin_list' => ['https://api.example.com'],
                'cors_allowed_headers' => ['Content-Type', 'X-AUTH-TOKEN'],
            ],
        ], $container);

        $this->assertFalse($container->getParameter('ov_json_rpc_api.strict_notifications'));
        $this->assertSame(10, $container->getParameter('ov_json_rpc_api.max_batch_size'));
        $this->assertSame(['https://api.example.com'], $container->getParameter('ov_json_rpc_api.access_control_allow_origin_list'));
        $this->assertSame(['Content-Type', 'X-AUTH-TOKEN'], $container->getParameter('ov_json_rpc_api.cors_allowed_headers'));
    }

    public function testLoadRegistersAutoconfigurationForApiMethodInterface(): void
    {
        $container = new ContainerBuilder();
        $extension = new OVJsonRPCAPIExtension();

        $extension->load([], $container);

        $autoconfigured = $container->getAutoconfiguredInstanceof();
        $this->assertArrayHasKey(\OV\JsonRPCAPIBundle\Core\ApiMethodInterface::class, $autoconfigured);
        $tags = $autoconfigured[\OV\JsonRPCAPIBundle\Core\ApiMethodInterface::class]->getTags();
        $this->assertArrayHasKey('ov.rpc.method', $tags);
    }

    public function testCallLoggerDefaultsToNullWhenLoggingDisabled(): void
    {
        $container = new ContainerBuilder();
        $extension = new OVJsonRPCAPIExtension();

        $extension->load([], $container);

        $this->assertSame(
            NullJsonRpcCallLogger::class,
            (string) $container->getAlias(JsonRpcCallLoggerInterface::class),
        );
    }

    public function testCallLoggerSwitchesToRealImplWhenLoggingEnabled(): void
    {
        $container = new ContainerBuilder();
        $extension = new OVJsonRPCAPIExtension();

        $extension->load([['logging' => ['enabled' => true]]], $container);

        $this->assertSame(
            JsonRpcCallLogger::class,
            (string) $container->getAlias(JsonRpcCallLoggerInterface::class),
        );
    }

    public function testLoggerAliasDefaultsToFrameworkLogger(): void
    {
        $container = new ContainerBuilder();
        $extension = new OVJsonRPCAPIExtension();

        $extension->load([], $container);

        $this->assertSame('logger', (string) $container->getAlias('ov_json_rpc_api.logger'));
    }

    public function testLoggerServiceConfigOverridesLoggerAlias(): void
    {
        $container = new ContainerBuilder();
        $extension = new OVJsonRPCAPIExtension();

        $extension->load([
            [
                'logging' => [
                    'enabled' => true,
                    'logger_service' => 'app.custom_psr3_logger',
                ],
            ],
        ], $container);

        $this->assertSame('app.custom_psr3_logger', (string) $container->getAlias('ov_json_rpc_api.logger'));
    }

    public function testLoggerServiceConfigAppliesEvenWhenLoggingDisabled(): void
    {
        $container = new ContainerBuilder();
        $extension = new OVJsonRPCAPIExtension();

        $extension->load([
            [
                'logging' => [
                    'enabled' => false,
                    'logger_service' => 'app.custom_psr3_logger',
                ],
            ],
        ], $container);

        $this->assertSame('app.custom_psr3_logger', (string) $container->getAlias('ov_json_rpc_api.logger'));
        $this->assertSame(
            NullJsonRpcCallLogger::class,
            (string) $container->getAlias(JsonRpcCallLoggerInterface::class),
            'Null call logger must still win — kill-switch sits above PSR-3 swap',
        );
    }

    public function testCallLoggerServiceConfigOverridesCallLogger(): void
    {
        $container = new ContainerBuilder();
        $extension = new OVJsonRPCAPIExtension();

        $extension->load([
            [
                'logging' => [
                    'enabled' => true,
                    'call_logger_service' => 'app.custom_call_logger',
                ],
            ],
        ], $container);

        $this->assertSame(
            'app.custom_call_logger',
            (string) $container->getAlias(JsonRpcCallLoggerInterface::class),
        );
    }

    public function testCallLoggerServiceIsIgnoredWhenLoggingDisabled(): void
    {
        $container = new ContainerBuilder();
        $extension = new OVJsonRPCAPIExtension();

        $extension->load([
            [
                'logging' => [
                    'enabled' => false,
                    'call_logger_service' => 'app.custom_call_logger',
                ],
            ],
        ], $container);

        $this->assertSame(
            NullJsonRpcCallLogger::class,
            (string) $container->getAlias(JsonRpcCallLoggerInterface::class),
            'enabled=false is a kill-switch that beats call_logger_service',
        );
    }

    public function testLoadRegistersNonEmptyDefaultMaskingPatternsAndNonZeroMaxBodyLength(): void
    {
        $container = new ContainerBuilder();
        $extension = new OVJsonRPCAPIExtension();

        $extension->load([], $container);

        $this->assertNotEmpty($container->getParameter('ov_json_rpc_api.logging.masking.key_patterns'));
        $this->assertGreaterThan(0, $container->getParameter('ov_json_rpc_api.logging.max_body_length'));
    }

    public function testLoadFailsContainerBuildOnInvalidMaskingRegex(): void
    {
        $container = new ContainerBuilder();
        $extension = new OVJsonRPCAPIExtension();

        $this->expectException(InvalidConfigurationException::class);

        $extension->load([
            [
                'logging' => [
                    'masking' => [
                        'key_patterns' => ['not a valid regex('],
                    ],
                ],
            ],
        ], $container);
    }

    public function testBothOverridesCanCoexist(): void
    {
        $container = new ContainerBuilder();
        $extension = new OVJsonRPCAPIExtension();

        $extension->load([
            [
                'logging' => [
                    'enabled' => true,
                    'logger_service' => 'app.custom_psr3_logger',
                    'call_logger_service' => 'app.custom_call_logger',
                ],
            ],
        ], $container);

        $this->assertSame('app.custom_psr3_logger', (string) $container->getAlias('ov_json_rpc_api.logger'));
        $this->assertSame(
            'app.custom_call_logger',
            (string) $container->getAlias(JsonRpcCallLoggerInterface::class),
        );
    }
}
