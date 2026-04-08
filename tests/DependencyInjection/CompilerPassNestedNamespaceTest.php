<?php

namespace OV\JsonRPCAPIBundle\Tests\DependencyInjection;

use OV\JsonRPCAPIBundle\DependencyInjection\CompilerPass;
use OV\JsonRPCAPIBundle\RPC\V1\Nested\MultiplyMethod;
use OV\JsonRPCAPIBundle\RPC\V1\SubtractMethod;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

final class CompilerPassNestedNamespaceTest extends TestCase
{
    private function createContainerWithMethod(string $className): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register($className, $className)
            ->addTag('ov.rpc.method')
            ->setPublic(true)
            ->setAutowired(true)
            ->setAutoconfigured(true);

        return $container;
    }

    /**
     * Baseline: method directly in RPC/V1/ namespace — version detection works.
     */
    public function testVersionDetectedFromDirectNamespace(): void
    {
        $container = $this->createContainerWithMethod(SubtractMethod::class);
        $compilerPass = new CompilerPass(new CamelCaseToSnakeCaseNameConverter());

        // Should NOT throw — namespace OV\...\RPC\V1 ends with V1
        $compilerPass->process($container);

        $this->assertTrue(true, 'CompilerPass processed V1 direct namespace without error');
    }

    /**
     * Bug reproduction: method in nested RPC/V1/Nested/ namespace fails
     * because the regex /V[0-9]+$/ requires V{N} at the END of the namespace string.
     * Namespace OV\...\RPC\V1\Nested ends with "Nested", not "V1".
     */
    public function testVersionDetectedFromNestedNamespace(): void
    {
        $container = $this->createContainerWithMethod(MultiplyMethod::class);
        $compilerPass = new CompilerPass(new CamelCaseToSnakeCaseNameConverter());

        // Should NOT throw — but currently does because regex uses $ anchor
        $compilerPass->process($container);

        $this->assertTrue(true, 'CompilerPass processed V1/Nested namespace without error');
    }
}
