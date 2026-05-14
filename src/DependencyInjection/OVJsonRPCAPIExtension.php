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

use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use OV\JsonRPCAPIBundle\Core\ApiMethodInterface;
use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcCallLogger;
use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcCallLoggerInterface;
use OV\JsonRPCAPIBundle\Core\Logging\NullJsonRpcCallLogger;

final class OVJsonRPCAPIExtension extends Extension
{
    /**
     * @noinspection PhpUnused
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(dirname(__DIR__) . '/../config')
        );
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter($this->getAlias() . '.swagger', $config['swagger']);
        $container->setParameter($this->getAlias() . '.access_control_allow_origin_list', $config['access_control_allow_origin_list']);
        $container->setParameter($this->getAlias() . '.strict_notifications', $config['strict_notifications']);
        $container->setParameter($this->getAlias() . '.allow_extra_fields', $config['allow_extra_fields']);
        $container->setParameter($this->getAlias() . '.expose_internal_errors', $config['expose_internal_errors']);
        $container->setParameter($this->getAlias() . '.cors_strict', $config['cors_strict']);
        $container->setParameter($this->getAlias() . '.max_payload_bytes', $config['max_payload_bytes']);
        $container->setParameter($this->getAlias() . '.max_json_depth', $config['max_json_depth']);
        $container->setParameter($this->getAlias() . '.max_batch_size', $config['max_batch_size']);
        $container->setParameter($this->getAlias() . '.max_dto_depth', $config['max_dto_depth']);
        $container->setParameter($this->getAlias() . '.max_array_param_size', $config['max_array_param_size']);

        // --- logging ---
        $loggingCfg = $config['logging'];
        $container->setParameter($this->getAlias() . '.logging.enabled', $loggingCfg['enabled']);
        $container->setParameter($this->getAlias() . '.logging.request_level', $loggingCfg['request_level']);
        $container->setParameter($this->getAlias() . '.logging.response_level', $loggingCfg['response_level']);
        $container->setParameter($this->getAlias() . '.logging.error_response_level', $loggingCfg['error_response_level']);
        $container->setParameter($this->getAlias() . '.logging.max_body_length', $loggingCfg['max_body_length']);
        $container->setParameter($this->getAlias() . '.logging.skip_plain_responses', $loggingCfg['skip_plain_responses']);
        $container->setParameter($this->getAlias() . '.logging.masking.placeholder', $loggingCfg['masking']['placeholder']);
        $container->setParameter($this->getAlias() . '.logging.masking.key_patterns', $loggingCfg['masking']['key_patterns']);

        $loggerImpl = $loggingCfg['enabled']
            ? JsonRpcCallLogger::class
            : NullJsonRpcCallLogger::class;

        $container->setAlias(JsonRpcCallLoggerInterface::class, $loggerImpl);

        $container->registerForAutoconfiguration(ApiMethodInterface::class)->addTag('ov.rpc.method');
    }

    public function getAlias(): string
    {
        return 'ov_json_rpc_api';
    }
}