<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\DependencyInjection;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\Core\Response\PlainResponseInterface;
use OV\JsonRPCAPIBundle\DependencyInjection\CompilerPass;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * Whether a response bypasses JSON-RPC wrapping is decided here, at compile time, from the declared
 * return type of call() - and every other test in this suite sets the resulting flag by hand on a
 * MethodSpec, so this method never ran under any of them. It only understood union return types,
 * which meant the simplest way to write such a method - a single named type - produced a JSON
 * envelope carrying `content`, `statusCode` and `charset` where a file was expected, with no error
 * anywhere. The documented example uses a union, which is why nobody noticed.
 */
final class CompilerPassPlainResponseTest extends TestCase
{
    public static function methodsReturningAPlainResponse(): array
    {
        return [
            'single named type' => [SingleTypePlainMethod::class],
            'union with an ordinary response' => [UnionTypePlainMethod::class],
            'the interface itself' => [InterfaceTypePlainMethod::class],
            'nullable plain response' => [NullableTypePlainMethod::class],
        ];
    }

    #[DataProvider('methodsReturningAPlainResponse')]
    public function testPlainResponseIsDetectedFromTheDeclaredReturnType(string $methodClass): void
    {
        self::assertTrue($this->plainResponseFlagOf($methodClass), 'the response bypasses JSON-RPC wrapping only when this flag is set');
    }

    public function testAnOrdinaryResponseIsNotMistakenForAPlainOne(): void
    {
        self::assertFalse($this->plainResponseFlagOf(OrdinaryMethod::class));
    }

    private function plainResponseFlagOf(string $methodClass): bool
    {
        $container = new ContainerBuilder();
        $container->register($methodClass, $methodClass)
            ->addTag('ov.rpc.method')
            ->setAutowired(true)
            ->setAutoconfigured(true);

        (new CompilerPass(new CamelCaseToSnakeCaseNameConverter()))->process($container);

        foreach ($container->getDefinitions() as $definition) {
            if ($definition->getClass() === MethodSpec::class) {
                return $this->plainResponseArgumentOf($definition);
            }
        }

        self::fail('no MethodSpec definition was produced');
    }

    private function plainResponseArgumentOf(Definition $methodSpec): bool
    {
        foreach ($methodSpec->getArguments() as $argument) {
            if (is_bool($argument)) {
                return $argument;
            }
        }

        self::fail('the MethodSpec definition carries no plain-response flag');
    }
}

final class PlainPayload extends Response implements PlainResponseInterface
{
}

final class OrdinaryPayload
{
    private string $value = 'x';

    public function getValue(): string
    {
        return $this->value;
    }
}

final class PlainRequest
{
    private array $params = [];

    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }
}

#[JsonRPCAPI(methodName: 'singlePlain', type: 'POST', version: 1, ignoreInSwagger: true)]
final class SingleTypePlainMethod
{
    public function call(PlainRequest $request): PlainPayload
    {
        return new PlainPayload();
    }
}

#[JsonRPCAPI(methodName: 'unionPlain', type: 'POST', version: 1, ignoreInSwagger: true)]
final class UnionTypePlainMethod
{
    public function call(PlainRequest $request): OrdinaryPayload|PlainPayload
    {
        return new PlainPayload();
    }
}

#[JsonRPCAPI(methodName: 'interfacePlain', type: 'POST', version: 1, ignoreInSwagger: true)]
final class InterfaceTypePlainMethod
{
    public function call(PlainRequest $request): PlainResponseInterface
    {
        return new PlainPayload();
    }
}

#[JsonRPCAPI(methodName: 'nullablePlain', type: 'POST', version: 1, ignoreInSwagger: true)]
final class NullableTypePlainMethod
{
    public function call(PlainRequest $request): ?PlainPayload
    {
        return new PlainPayload();
    }
}

#[JsonRPCAPI(methodName: 'ordinary', type: 'POST', version: 1, ignoreInSwagger: true)]
final class OrdinaryMethod
{
    public function call(PlainRequest $request): OrdinaryPayload
    {
        return new OrdinaryPayload();
    }
}
