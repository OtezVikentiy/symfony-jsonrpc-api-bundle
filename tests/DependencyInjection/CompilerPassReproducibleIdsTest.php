<?php

namespace OV\JsonRPCAPIBundle\Tests\DependencyInjection;

use OV\JsonRPCAPIBundle\DependencyInjection\CompilerPass;
use OV\JsonRPCAPIBundle\RPC\V1\SubtractMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

final class CompilerPassReproducibleIdsTest extends TestCase
{
    /**
     * A prior implementation minted the metadata service ids with uniqid(),
     * so every cache warmup produced a different container and debug:container
     * diffs between two builds of the same source were never empty.
     */
    public function testMetadataServiceIdsAreIdenticalAcrossSeparateBuilds(): void
    {
        $firstIds = $this->generatedMetadataServiceIds();
        $secondIds = $this->generatedMetadataServiceIds();

        $this->assertNotEmpty($firstIds);
        $this->assertSame($firstIds, $secondIds);
    }

    private function generatedMetadataServiceIds(): array
    {
        $container = new ContainerBuilder();
        $container->register(SubtractMethod::class, SubtractMethod::class)
            ->addTag('ov.rpc.method')
            ->setPublic(true)
            ->setAutowired(true)
            ->setAutoconfigured(true);

        $compilerPass = new CompilerPass(new CamelCaseToSnakeCaseNameConverter());
        $compilerPass->process($container);

        $ids = array_filter(
            array_keys($container->getDefinitions()),
            static fn (string $id): bool => str_starts_with($id, 'OV_JSON_RPC_API_'),
        );
        sort($ids);

        return $ids;
    }
}
