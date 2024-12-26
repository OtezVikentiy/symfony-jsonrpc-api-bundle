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

        $container->registerForAutoconfiguration(ApiMethodInterface::class)->addTag('ov.rpc.method');
    }

    public function getAlias(): string
    {
        return 'ov_json_rpc_api';
    }
}