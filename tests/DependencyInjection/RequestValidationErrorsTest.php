<?php

namespace OV\JsonRPCAPIBundle\Tests\DependencyInjection;

use Exception;
use OV\JsonRPCAPIBundle\DependencyInjection\CompilerPass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

final class RequestValidationErrorsTest extends TestCase
{
    /**
     * analyzeRequestClass() is private, so it is reached through
     * ReflectionMethod::getClosure(). invoke() coerces arguments in weak
     * mode regardless of declare(strict_types=1) in the source file, which
     * would mask type errors this test needs to see.
     */
    private function analyze(string $methodClass): array
    {
        $compilerPass = new CompilerPass(new CamelCaseToSnakeCaseNameConverter());

        $reflectionMethod = new ReflectionMethod(CompilerPass::class, 'analyzeRequestClass');
        $analyzeRequestClass = $reflectionMethod->getClosure($compilerPass);

        return $analyzeRequestClass(new ReflectionClass($methodClass), $methodClass);
    }

    public function testMissingSetterFailsWithPropertyAndClassName(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/tokens/');
        $this->expectExceptionMessageMatches('/RequestValidationMissingSetterRequest/');

        $this->analyze(RequestValidationMissingSetterMethod::class);
    }

    public function testUntypedPropertyFailsWithPropertyAndClassName(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/value/');
        $this->expectExceptionMessageMatches('/RequestValidationUntypedPropertyRequest/');

        $this->analyze(RequestValidationUntypedPropertyMethod::class);
    }

    public function testUnionTypePropertyFailsWithPropertyAndClassName(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/value/');
        $this->expectExceptionMessageMatches('/RequestValidationUnionTypePropertyRequest/');

        $this->analyze(RequestValidationUnionTypePropertyMethod::class);
    }
}

final class RequestValidationToken
{
    private string $value = '';

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }
}

final class RequestValidationMissingSetterRequest
{
    private array $tokens = [];

    public function getTokens(): array
    {
        return $this->tokens;
    }

    public function addToken(RequestValidationToken $token): void
    {
        $this->tokens[] = $token;
    }
}

final class RequestValidationMissingSetterMethod
{
    public function call(RequestValidationMissingSetterRequest $request): array
    {
        return [];
    }
}

final class RequestValidationUntypedPropertyRequest
{
    private $value;

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value): void
    {
        $this->value = $value;
    }
}

final class RequestValidationUntypedPropertyMethod
{
    public function call(RequestValidationUntypedPropertyRequest $request): array
    {
        return [];
    }
}

final class RequestValidationUnionTypePropertyRequest
{
    private int|string $value = 0;

    public function getValue(): int|string
    {
        return $this->value;
    }

    public function setValue(int|string $value): void
    {
        $this->value = $value;
    }
}

final class RequestValidationUnionTypePropertyMethod
{
    public function call(RequestValidationUnionTypePropertyRequest $request): array
    {
        return [];
    }
}
