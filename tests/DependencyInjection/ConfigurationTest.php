<?php

namespace OV\JsonRPCAPIBundle\Tests\DependencyInjection;

use OV\JsonRPCAPIBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
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
}
