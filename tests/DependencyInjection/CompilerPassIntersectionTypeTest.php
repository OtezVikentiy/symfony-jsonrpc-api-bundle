<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\DependencyInjection;

use Exception;
use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\Core\Response\PlainResponseInterface;
use OV\JsonRPCAPIBundle\DependencyInjection\CompilerPass;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * PHP 8.2+ intersection types (`A&B`) can appear anywhere a plain class type
 * can: as one arm of a `call()` return union, or as a request DTO setter
 * parameter / getter return type. CompilerPass reflects those signatures at
 * container-compile time; before this test's fixes, hitting an intersection
 * type in any of these three spots called ReflectionType::getName(), a
 * method neither ReflectionIntersectionType nor a bare ReflectionType
 * declares, and crashed with an uncaught Error instead of the clear
 * compile-time exception the rest of this file already raises for other
 * malformed method/DTO shapes.
 */
final class CompilerPassIntersectionTypeTest extends TestCase
{
    private function process(string $methodClass): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register($methodClass, $methodClass)
            ->addTag('ov.rpc.method')
            ->setPublic(true)
            ->setAutowired(true)
            ->setAutoconfigured(true);

        (new CompilerPass(new CamelCaseToSnakeCaseNameConverter()))->process($container);

        return $container;
    }

    public function testIntersectionArmInCallReturnUnionIsSkippedNotCrashed(): void
    {
        $container = $this->process(IntersectionReturnMethod::class);

        $plainResponse = $this->methodSpecDefinition($container)->getArgument(6);
        $this->assertTrue(
            $plainResponse,
            'The non-intersection union member (PlainSide) implements PlainResponseInterface and should still be detected once the unreadable intersection member is skipped.',
        );
    }

    private function methodSpecDefinition(ContainerBuilder $container): Definition
    {
        foreach ($container->getDefinitions() as $definition) {
            if ($definition->getClass() === MethodSpec::class) {
                return $definition;
            }
        }

        $this->fail('No MethodSpec definition was registered.');
    }

    public function testIntersectionSetterParameterThrowsClearException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/invalid data type in setter setValue/');

        $this->process(IntersectionSetterMethod::class);
    }

    public function testIntersectionGetterReturnTypeThrowsClearException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/invalid data type in getter getValue/');

        $this->process(IntersectionGetterMethod::class);
    }

    public function testServiceWithoutResolvableClassThrowsClearException(): void
    {
        $container = new ContainerBuilder();
        $container->register('untyped.rpc.service')->addTag('ov.rpc.method');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/has no resolvable class/');

        (new CompilerPass(new CamelCaseToSnakeCaseNameConverter()))->process($container);
    }

    /**
     * A DTO property typed with a pure intersection (no union involved at all) used to raise
     * "has a union type, which is not supported" - technically correct that the type is
     * unsupported, but naming the wrong construct sends whoever reads it looking for a `|` in a
     * signature that only has `&`. The message now names the actual declared type instead of
     * assuming which unsupported construct it is.
     */
    public function testIntersectionPropertyTypeMessageNamesTheActualConstruct(): void
    {
        try {
            $this->process(IntersectionPropertyMethod::class);
            $this->fail('Expected an exception for the intersection-typed property.');
        } catch (Exception $e) {
            $this->assertStringContainsString('IntersectionMarkerA&', $e->getMessage());
            $this->assertStringContainsString('IntersectionMarkerB', $e->getMessage());
            $this->assertStringNotContainsString('union type', $e->getMessage());
        }
    }
}

interface IntersectionMarkerA
{
}

interface IntersectionMarkerB
{
}

final class IntersectionResponseNotPlain implements IntersectionMarkerA, IntersectionMarkerB
{
}

final class IntersectionPlainSide extends Response implements PlainResponseInterface
{
}

final class IntersectionReturnRequest
{
}

#[JsonRPCAPI(methodName: 'intersectionReturn', type: 'POST', version: 1, ignoreInSwagger: true)]
final class IntersectionReturnMethod
{
    public function call(IntersectionReturnRequest $request): (IntersectionMarkerA&IntersectionMarkerB)|IntersectionPlainSide
    {
        return new IntersectionPlainSide();
    }
}

final class IntersectionSetterRequest
{
    private string $value = '';

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(IntersectionMarkerA&IntersectionMarkerB $value): void
    {
    }
}

#[JsonRPCAPI(methodName: 'intersectionSetter', type: 'POST', version: 1, ignoreInSwagger: true)]
final class IntersectionSetterMethod
{
    public function call(IntersectionSetterRequest $request): IntersectionResponseNotPlain
    {
        return new IntersectionResponseNotPlain();
    }
}

final class IntersectionGetterRequest
{
    private string $value = '';

    public function getValue(): IntersectionMarkerA&IntersectionMarkerB
    {
        throw new RuntimeException('never called - CompilerPass fails before any instance is built');
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }
}

#[JsonRPCAPI(methodName: 'intersectionGetter', type: 'POST', version: 1, ignoreInSwagger: true)]
final class IntersectionGetterMethod
{
    public function call(IntersectionGetterRequest $request): IntersectionResponseNotPlain
    {
        return new IntersectionResponseNotPlain();
    }
}

final class IntersectionPropertyRequest
{
    private IntersectionMarkerA&IntersectionMarkerB $value;
}

#[JsonRPCAPI(methodName: 'intersectionProperty', type: 'POST', version: 1, ignoreInSwagger: true)]
final class IntersectionPropertyMethod
{
    public function call(IntersectionPropertyRequest $request): IntersectionResponseNotPlain
    {
        return new IntersectionResponseNotPlain();
    }
}
