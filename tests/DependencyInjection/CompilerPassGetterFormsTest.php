<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\DependencyInjection;

use Exception;
use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\DependencyInjection\CompilerPass;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * A getter is resolved from three candidates - getX, isX, and the bare accessor x - but two places
 * asked the question and only one of them knew that. Validator collection ran first and derived a
 * single rigid name: isX for a boolean property, getX otherwise. So a boolean named $isActive, whose
 * natural accessor is isActive(), aborted the container build demanding a method called isIsActive(),
 * and the bare accessor documented by the other resolver could not be reached at all. Both are
 * ordinary DTO shapes, and the error message sent the developer off to write a method nobody wants.
 */
final class CompilerPassGetterFormsTest extends TestCase
{
    public function testBooleanPropertyAlreadyNamedIsSomethingCompiles(): void
    {
        $container = $this->process(FlagMethod::class);

        self::assertSame('isActive', $this->requestGetters($container)['isActive']);
    }

    public function testBareAccessorCompiles(): void
    {
        $container = $this->process(BareMethod::class);

        self::assertSame('title', $this->requestGetters($container)['title']);
    }

    public function testConventionalBooleanGetterStillCompiles(): void
    {
        $container = $this->process(PlainMethod::class);

        self::assertSame('isActive', $this->requestGetters($container)['active']);
    }

    public function testPropertyWithNoAccessorAtAllNamesEveryFormItLookedFor(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('has no accessible getter (expected one of getSecret, isSecret, or secret)');

        $this->process(NoGetterMethod::class);
    }

    private function process(string $methodClass): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register($methodClass, $methodClass)
            ->addTag('ov.rpc.method')
            ->setAutowired(true)
            ->setAutoconfigured(true);

        (new CompilerPass(new CamelCaseToSnakeCaseNameConverter()))->process($container);

        return $container;
    }

    /**
     * @return array<string, string>
     */
    private function requestGetters(ContainerBuilder $container): array
    {
        foreach ($container->getDefinitions() as $definition) {
            if ($definition->getClass() === MethodSpec::class) {
                return $this->requestMetadataOf($container, $definition)->getArgument(3);
            }
        }

        self::fail('no MethodSpec definition was produced');
    }

    private function requestMetadataOf(ContainerBuilder $container, Definition $methodSpec): Definition
    {
        $metadata = $methodSpec->getArgument(3);

        if ($metadata instanceof Reference) {
            $metadata = $container->getDefinition((string) $metadata);
        }

        self::assertInstanceOf(Definition::class, $metadata);

        return $metadata;
    }
}

final class FlagRequest
{
    private bool $isActive;

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }
}

#[JsonRPCAPI(methodName: 'flag', type: 'POST', version: 1, ignoreInSwagger: true)]
final class FlagMethod
{
    public function call(FlagRequest $request): array
    {
        return [];
    }
}

final class BareRequest
{
    private string $title;

    public function title(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}

#[JsonRPCAPI(methodName: 'bare', type: 'POST', version: 1, ignoreInSwagger: true)]
final class BareMethod
{
    public function call(BareRequest $request): array
    {
        return [];
    }
}

final class PlainBoolRequest
{
    private bool $active;

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}

#[JsonRPCAPI(methodName: 'plain', type: 'POST', version: 1, ignoreInSwagger: true)]
final class PlainMethod
{
    public function call(PlainBoolRequest $request): array
    {
        return [];
    }
}

final class NoGetterRequest
{
    private string $secret;

    public function setSecret(string $secret): void
    {
        $this->secret = $secret;
    }
}

#[JsonRPCAPI(methodName: 'nogetter', type: 'POST', version: 1, ignoreInSwagger: true)]
final class NoGetterMethod
{
    public function call(NoGetterRequest $request): array
    {
        return [];
    }
}
