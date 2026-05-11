<?php

namespace OV\JsonRPCAPIBundle\Tests\Security;

use OV\JsonRPCAPIBundle\Command\SwaggerGenerate;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use OV\JsonRPCAPIBundle\RPC\V1\Sum\SumRequest;
use OV\JsonRPCAPIBundle\RPC\V1\SumMethod;
use OV\JsonRPCAPIBundle\Swagger\SwaggerSchemaBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SwaggerGenerateSecurityTest extends TestCase
{
    private string $sandboxDir = '';

    protected function setUp(): void
    {
        $this->sandboxDir = sys_get_temp_dir() . '/ovrpc-swagger-' . bin2hex(random_bytes(4));
        mkdir($this->sandboxDir);
    }

    protected function tearDown(): void
    {
        if ($this->sandboxDir !== '' && is_dir($this->sandboxDir)) {
            foreach (glob($this->sandboxDir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->sandboxDir);
        }
    }

    public function testWritesYamlWhenDirectoryIsValid(): void
    {
        $tester = $this->buildCommandTester($this->sandboxDir);

        $exit = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertFileExists($this->sandboxDir . '/api_v1.yaml');
        $this->assertNotEmpty(file_get_contents($this->sandboxDir . '/api_v1.yaml'));
    }

    public function testRejectsInvalidDirectory(): void
    {
        $tester = $this->buildCommandTester('/nonexistent-target-' . bin2hex(random_bytes(4)));

        $exit = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('does not exist or is not writable', $tester->getDisplay());
    }

    public function testRejectsEmptyDirectory(): void
    {
        $tester = $this->buildCommandTester('');

        $exit = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exit);
    }

    private function buildCommandTester(string $path): CommandTester
    {
        $application = new Application();
        $application->add(new SwaggerGenerate(
            $path,
            [
                'api_v1' => [
                    'api_version' => '1',
                    'base_path' => 'https://api.example.com',
                    'base_path_description' => 'prod',
                    'test_path' => null,
                    'test_path_description' => null,
                    'base_path_variables' => [],
                    'test_path_variables' => [],
                    'auth_token_name' => 'X-AUTH-TOKEN',
                    'auth_token_test_value' => 'test',
                    'info' => [
                        'title' => 'title',
                        'description' => 'description',
                        'terms_of_service_url' => 'tos',
                        'contact' => ['name' => 'n', 'url' => 'u', 'email' => 'e'],
                        'license' => 'MIT',
                        'licenseUrl' => 'license-url',
                    ],
                ],
            ],
            new SwaggerSchemaBuilder($this->buildMethodSpecCollection()),
            false,
        ));

        return new CommandTester($application->find('ov:swagger:generate'));
    }

    private function buildMethodSpecCollection(): MethodSpecCollection
    {
        $collection = new MethodSpecCollection();
        $collection->addMethodSpec(1, 'sum', new MethodSpec(
            methodClass: SumMethod::class,
            requestType: 'POST',
            methodName: 'sum',
            requestMetadata: new RequestMetadata(
                request: SumRequest::class,
                allParameters: [['name' => 'params', 'type' => 'array']],
                requiredParameters: [],
                requestGetters: ['params' => 'getParams'],
                requestSetters: ['params' => 'setParams'],
                requestAdders: [],
                validators: ['params' => 'array'],
            ),
            swaggerMetadata: new SwaggerMetadata(
                summary: '',
                description: '',
                ignoreInSwagger: false,
                tags: ['mathematic'],
            ),
        ));

        return $collection;
    }
}
