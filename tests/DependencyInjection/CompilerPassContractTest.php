<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\DependencyInjection;

use Exception;
use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\Core\PostProcessorInterface;
use OV\JsonRPCAPIBundle\Core\PreProcessorInterface;
use OV\JsonRPCAPIBundle\DependencyInjection\CompilerPass;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * Everything CompilerPass decides is decided once, at container build time, from reflection over the
 * application's own classes - and every other test in this suite hands the result over by hand,
 * constructing a MethodSpec with the answers already filled in. That left the code producing those
 * answers barely exercised, and three defects were found there in a row: a plain response declared
 * with a single return type went unrecognised, a getter form the resolver documented was
 * unreachable, and an omitted OpenAPI block aborted generation. Each one lived in a branch no test
 * walked.
 *
 * This file walks them. Every case builds a real container and asserts what the pass produces or
 * refuses, so a malformed DTO fails here rather than at a consumer's first deploy.
 */
final class CompilerPassContractTest extends TestCase
{
    // ---- what the pass accepts ----

    public function testVersionComesFromTheAttributeWhenGiven(): void
    {
        self::assertSame(7, $this->registeredVersionOf(ExplicitVersionMethod::class));
    }

    public function testVersionIsDerivedFromTheNamespaceWhenTheAttributeOmitsIt(): void
    {
        // The fixture lives in ...\RPC\V1\, which is the convention the error message advises.
        self::assertSame(1, $this->registeredVersionOf(\OV\JsonRPCAPIBundle\RPC\V1\SubtractMethod::class));
    }

    public function testAServiceWithoutTheAttributeIsSkippedRatherThanRefused(): void
    {
        $container = $this->process(NotAnRpcMethod::class);

        foreach ($container->getDefinitions() as $definition) {
            self::assertNotSame(MethodSpec::class, $definition->getClass(), 'a tagged service with no attribute is not an RPC method');
        }
    }

    public function testProcessorsAreDetectedOnTheParentClass(): void
    {
        $spec = $this->specOf(InheritedProcessorsMethod::class);

        self::assertTrue($spec->getArgument(7), 'a pre-processor declared by the parent counts');
        self::assertTrue($spec->getArgument(8), 'a post-processor declared by the parent counts');
    }

    public function testProcessorsAreAbsentWhenNothingDeclaresThem(): void
    {
        $spec = $this->specOf(ExplicitVersionMethod::class);

        self::assertFalse($spec->getArgument(7));
        self::assertFalse($spec->getArgument(8));
    }

    public function testAcceptsMultipartTravelsFromTheAttributeToTheSpec(): void
    {
        self::assertTrue($this->specOf(MultipartMethod::class)->getArgument(10));
    }

    public function testAMethodThatSaysNothingAboutMultipartDoesNotAcceptIt(): void
    {
        // The gate is the only thing standing between a globally enabled transport and every method
        // already written, so the absent argument has to mean "no".
        self::assertFalse($this->specOf(ExplicitVersionMethod::class)->getArgument(10));
    }

    public function testAMethodTakingNoParameterCompiles(): void
    {
        $container = $this->process(NoParameterMethod::class);
        $metadata = $this->requestMetadataOf($container, $this->specOf(NoParameterMethod::class));

        self::assertNull($metadata->getArgument(0), 'no incoming parameter means no request DTO');
    }

    public function testAnUntypedSetterParameterIsSkippedRatherThanRefused(): void
    {
        $spec = $this->specOf(UntypedSetterMethod::class);

        self::assertInstanceOf(Definition::class, $spec, 'an untyped setter is not something to refuse over');
    }

    // ---- what the pass refuses, and how clearly ----

    public function testAMethodWithoutCallIsRefusedByName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('call is not defined');

        $this->process(NoCallMethod::class);
    }

    public function testAMethodTakingTwoParametersIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('one or zero incoming parameters');

        $this->process(TwoParameterMethod::class);
    }

    public function testAScalarParameterTypeIsRefused(): void
    {
        $this->expectException(RuntimeException::class);

        $this->process(ScalarParameterMethod::class);
    }

    public function testAVersionThatCannotBeResolvedIsRefusedWithAdvice(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Version for API endpoint');

        $this->process(UnversionedMethod::class);
    }

    public function testAPropertyWithNoAccessibleGetterIsRefusedByName(): void
    {
        $this->expectException(Exception::class);
        // Names the three forms it looked for, for this property - not a literal "getX, isX, or x".
        $this->expectExceptionMessage('has no accessible getter (expected one of getSecret, isSecret, or secret)');

        $this->process(NoGetterAnywhereMethod::class);
    }

    /**
     * A namespace segment of V0 parses to zero, which is no version at all. The message differs from
     * the one for a missing segment, because the mistake does.
     */
    public function testAZeroVersionInTheNamespaceIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not defined or zero');

        $this->process(\OV\JsonRPCAPIBundle\Tests\Fixtures\V0\ZeroVersionMethod::class);
    }

    /**
     * Constructor parameters go through the same collector as properties, but reach it as
     * ReflectionParameter rather than ReflectionProperty - a separate branch, and the one that
     * decides whether a required parameter carries a default.
     */
    public function testAConstructorParameterWithADefaultIsRecordedAsOptional(): void
    {
        $container = $this->process(ConstructorDefaultMethod::class);
        $metadata = $this->requestMetadataOf($container, $this->specOf(ConstructorDefaultMethod::class));

        $required = $metadata->getArgument(2);

        self::assertSame('limit', $required[0]['name']);
        self::assertArrayHasKey('defaultValue', $required[0], 'the constructor default must be carried through');
        self::assertSame(10, $required[0]['defaultValue']);
    }

    /**
     * The constructor is the one place a type can go missing without getValidatorsForRequest()
     * noticing: it vets properties, and a DTO may declare `private int $limit` while its constructor
     * takes a bare `$limit`. Reading the type off that aborted the build with "Call to a member
     * function getName() on null" - the exact shape of failure this pass was taught to stop
     * producing, naming neither the parameter nor the class.
     */
    public function testAnUntypedConstructorParameterIsRefusedByName(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Parameter limit of class');
        $this->expectExceptionMessage('has no declared type');

        $this->process(UntypedConstructorMethod::class);
    }

    public function testAUnionTypedConstructorParameterIsRefusedByName(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('not a single named type');

        $this->process(UnionConstructorMethod::class);
    }

    private function registeredVersionOf(string $methodClass): int
    {
        $container = $this->process($methodClass);

        foreach ($container->getDefinitions() as $definition) {
            foreach ($definition->getMethodCalls() as [$method, $arguments]) {
                if ($method === 'addMethodSpec') {
                    // The pass passes them by name, not by position.
                    return $arguments['$version'] ?? $arguments[0];
                }
            }
        }

        self::fail('nothing registered a method spec');
    }

    private function requestMetadataOf(ContainerBuilder $container, Definition $spec): Definition
    {
        $metadata = $spec->getArgument(3);

        return $metadata instanceof Reference ? $container->getDefinition((string) $metadata) : $metadata;
    }

    private function specOf(string $methodClass): Definition
    {
        $container = $this->process($methodClass);

        foreach ($container->getDefinitions() as $definition) {
            if ($definition->getClass() === MethodSpec::class) {
                return $definition;
            }
        }

        self::fail('no MethodSpec definition was produced');
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
}

final class ContractRequest
{
    private string $title = '';

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}

#[JsonRPCAPI(methodName: 'multipart', type: 'POST', version: 1, ignoreInSwagger: true, acceptsMultipart: true)]
final class MultipartMethod
{
    public function call(ContractRequest $request): array
    {
        return [];
    }
}

#[JsonRPCAPI(methodName: 'explicitVersion', type: 'POST', version: 7, ignoreInSwagger: true)]
final class ExplicitVersionMethod
{
    public function call(ContractRequest $request): array
    {
        return [];
    }
}

/**
 * Tagged, but carrying no attribute - the shape an application produces when it tags a service by
 * mistake, or autoconfigures a base class.
 */
final class NotAnRpcMethod
{
    public function call(ContractRequest $request): array
    {
        return [];
    }
}

abstract class ProcessorBase implements PreProcessorInterface, PostProcessorInterface
{
    public function getPreProcessors(): array
    {
        return [];
    }

    public function getPostProcessors(): array
    {
        return [];
    }
}

#[JsonRPCAPI(methodName: 'inheritedProcessors', type: 'POST', version: 1, ignoreInSwagger: true)]
final class InheritedProcessorsMethod extends ProcessorBase
{
    public function call(ContractRequest $request): array
    {
        return [];
    }
}

#[JsonRPCAPI(methodName: 'noParameter', type: 'POST', version: 1, ignoreInSwagger: true)]
final class NoParameterMethod
{
    public function call(): array
    {
        return [];
    }
}

final class UntypedSetterRequest
{
    private string $title = '';

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle($title): void
    {
        $this->title = (string) $title;
    }
}

#[JsonRPCAPI(methodName: 'untypedSetter', type: 'POST', version: 1, ignoreInSwagger: true)]
final class UntypedSetterMethod
{
    public function call(UntypedSetterRequest $request): array
    {
        return [];
    }
}

#[JsonRPCAPI(methodName: 'noCall', type: 'POST', version: 1, ignoreInSwagger: true)]
final class NoCallMethod
{
    public function somethingElse(): void
    {
    }
}

#[JsonRPCAPI(methodName: 'twoParameters', type: 'POST', version: 1, ignoreInSwagger: true)]
final class TwoParameterMethod
{
    public function call(ContractRequest $request, string $extra): array
    {
        return [];
    }
}

#[JsonRPCAPI(methodName: 'scalarParameter', type: 'POST', version: 1, ignoreInSwagger: true)]
final class ScalarParameterMethod
{
    public function call(string $request): array
    {
        return [];
    }
}

/**
 * No version in the attribute, and this namespace carries no V<n> segment either.
 */
#[JsonRPCAPI(methodName: 'unversioned', type: 'POST', ignoreInSwagger: true)]
final class UnversionedMethod
{
    public function call(ContractRequest $request): array
    {
        return [];
    }
}

final class NoGetterAnywhereRequest
{
    private string $secret = '';

    public function setSecret(string $secret): void
    {
        $this->secret = $secret;
    }
}

final class ConstructorDefaultRequest
{
    private int $limit;

    public function __construct(int $limit = 10)
    {
        $this->limit = $limit;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }
}

#[JsonRPCAPI(methodName: 'constructorDefault', type: 'POST', version: 1, ignoreInSwagger: true)]
final class ConstructorDefaultMethod
{
    public function call(ConstructorDefaultRequest $request): array
    {
        return [];
    }
}

final class UntypedConstructorRequest
{
    private int $limit = 0;

    public function __construct($limit = 0)
    {
        $this->limit = (int) $limit;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }
}

#[JsonRPCAPI(methodName: 'untypedConstructor', type: 'POST', version: 1, ignoreInSwagger: true)]
final class UntypedConstructorMethod
{
    public function call(UntypedConstructorRequest $request): array
    {
        return [];
    }
}

final class UnionConstructorRequest
{
    private int $limit = 0;

    public function __construct(int|string $limit = 0)
    {
        $this->limit = (int) $limit;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }
}

#[JsonRPCAPI(methodName: 'unionConstructor', type: 'POST', version: 1, ignoreInSwagger: true)]
final class UnionConstructorMethod
{
    public function call(UnionConstructorRequest $request): array
    {
        return [];
    }
}

#[JsonRPCAPI(methodName: 'noGetterAnywhere', type: 'POST', version: 1, ignoreInSwagger: true)]
final class NoGetterAnywhereMethod
{
    public function call(NoGetterAnywhereRequest $request): array
    {
        return [];
    }
}
