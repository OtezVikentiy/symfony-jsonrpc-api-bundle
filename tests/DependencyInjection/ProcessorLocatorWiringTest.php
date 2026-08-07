<?php

namespace OV\JsonRPCAPIBundle\Tests\DependencyInjection;

use OV\JsonRPCAPIBundle\Core\Services\RequestHandler;
use OV\JsonRPCAPIBundle\DependencyInjection\CompilerPass;
use OV\JsonRPCAPIBundle\RPC\V1\SubtractMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * CompilerPass builds a ServiceLocator restricted to ov.rpc.method-tagged
 * services and binds it into RequestHandler's $processorLocator argument.
 * These tests drive that wiring through a real ContainerBuilder compile
 * rather than through hand-rolled mocks, so a regression in the locator
 * construction itself — not just in a test double shaped to match it —
 * would be caught here.
 */
final class ProcessorLocatorWiringTest extends TestCase
{
    private function buildContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();

        $container->register(SubtractMethod::class, SubtractMethod::class)
            ->addTag('ov.rpc.method')
            ->setPublic(false)
            ->setAutowired(true)
            ->setAutoconfigured(true);

        $container->register(RequestHandler::class, ProcessorLocatorProbe::class)
            ->setArguments(['$processorLocator' => null])
            ->setPublic(true);

        return $container;
    }

    public function testCompiledLocatorResolvesTaggedProcessorByClassName(): void
    {
        $container = $this->buildContainer();
        $compilerPass = new CompilerPass(new CamelCaseToSnakeCaseNameConverter());
        $compilerPass->process($container);
        $container->compile();

        /** @var ProcessorLocatorProbe $probe */
        $probe = $container->get(RequestHandler::class);

        $this->assertInstanceOf(ServiceLocator::class, $probe->processorLocator);
        $this->assertInstanceOf(SubtractMethod::class, $probe->processorLocator->get(SubtractMethod::class));
    }

    public function testTaggedProcessorServiceIsNotForcedPublic(): void
    {
        $container = $this->buildContainer();
        $compilerPass = new CompilerPass(new CamelCaseToSnakeCaseNameConverter());
        $compilerPass->process($container);

        $this->assertFalse(
            $container->getDefinition(SubtractMethod::class)->isPublic(),
            'CompilerPass must resolve processors through the locator, not by making every RPC method a public service.',
        );
    }
}

final class ProcessorLocatorProbe
{
    public function __construct(
        public readonly ServiceLocator $processorLocator,
    ) {
    }
}
