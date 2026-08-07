<?php

namespace OV\JsonRPCAPIBundle\Tests\DependencyInjection;

use Exception;
use OV\JsonRPCAPIBundle\DependencyInjection\CompilerPass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

final class MethodNameBindingTest extends TestCase
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

    private function findParameter(array $allParameters, string $name): array
    {
        foreach ($allParameters as $parameter) {
            if ($parameter['name'] === $name) {
                return $parameter;
            }
        }

        $this->fail(sprintf('Parameter %s was not found among analyzed properties', $name));
    }

    public function testGettersAreBoundByExactPropertyNameNotSubstring(): void
    {
        $result = $this->analyze(MethodNameBindingFixtureMethod::class);

        $this->assertSame('getId', $result['requestGetters']['id']);
        $this->assertSame('getUserId', $result['requestGetters']['userId']);
        $this->assertSame('getToken', $result['requestGetters']['token']);
        $this->assertSame('getRefreshToken', $result['requestGetters']['refreshToken']);
        $this->assertSame('getName', $result['requestGetters']['name']);
        $this->assertSame('getFullName', $result['requestGetters']['fullName']);
    }

    public function testSettersAreKeyedByPropertyNameNotSetterParameterName(): void
    {
        $result = $this->analyze(MethodNameBindingFixtureMethod::class);

        $this->assertSame('setId', $result['requestSetters']['id']);
        $this->assertSame('setUserId', $result['requestSetters']['userId']);
        $this->assertNotSame(
            $result['requestSetters']['id'],
            $result['requestSetters']['userId'],
            'setUserId(int $id) must bind under the userId property, not under id',
        );
    }

    public function testBareAccessorIsUsedWhenNoGetOrIsPrefixExists(): void
    {
        $result = $this->analyze(MethodNameBindingBareAccessorMethod::class);

        $this->assertSame('active', $result['requestGetters']['active']);
    }

    public function testAdderElementTypeIsAppliedOnlyToItsOwnProperty(): void
    {
        $result = $this->analyze(MethodNameBindingFixtureMethod::class);

        $this->assertSame('addToken', $result['requestAdders']['tokens']);

        $tokens = $this->findParameter($result['allParameters'], 'tokens');
        $this->assertSame(MethodNameBindingFixtureToken::class, $tokens['type']);

        // tokenCount contains "token" as a substring; under the old
        // str_contains matching its type was overwritten to Token::class.
        $tokenCount = $this->findParameter($result['allParameters'], 'tokenCount');
        $this->assertSame('int', $tokenCount['type']);
    }

    public function testMissingAccessibleGetterFailsContainerCompilation(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/secret/');

        $this->analyze(MethodNameBindingMissingGetterMethod::class);
    }
}

final class MethodNameBindingFixtureToken
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

final class MethodNameBindingFixtureRequest
{
    private int $id;
    private int $userId;
    private string $token;
    private string $refreshToken;
    private string $name;
    private string $fullName;
    private array $tokens = [];
    private int $tokenCount = 0;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $id): void
    {
        $this->userId = $id;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(string $refreshToken): void
    {
        $this->refreshToken = $refreshToken;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): void
    {
        $this->fullName = $fullName;
    }

    public function getTokens(): array
    {
        return $this->tokens;
    }

    public function setTokens(array $tokens): void
    {
        $this->tokens = $tokens;
    }

    public function addToken(MethodNameBindingFixtureToken $token): void
    {
        $this->tokens[] = $token;
    }

    public function getTokenCount(): int
    {
        return $this->tokenCount;
    }

    public function setTokenCount(int $tokenCount): void
    {
        $this->tokenCount = $tokenCount;
    }
}

final class MethodNameBindingFixtureMethod
{
    public function call(MethodNameBindingFixtureRequest $request): array
    {
        return [];
    }
}

final class MethodNameBindingBareAccessorRequest
{
    private bool $active = false;

    // Kept private on purpose: getValidatorsForRequest() looks up an
    // is-prefixed method unconditionally for bool properties regardless of
    // visibility, while resolveGetter() only accepts a public one and must
    // fall through to the bare accessor below.
    private function isActive(): bool
    {
        return $this->active;
    }

    public function active(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}

final class MethodNameBindingBareAccessorMethod
{
    public function call(MethodNameBindingBareAccessorRequest $request): array
    {
        return [];
    }
}

final class MethodNameBindingMissingGetterRequest
{
    private int $secret = 0;

    private function getSecret(): int
    {
        return $this->secret;
    }

    public function setSecret(int $secret): void
    {
        $this->secret = $secret;
    }
}

final class MethodNameBindingMissingGetterMethod
{
    public function call(MethodNameBindingMissingGetterRequest $request): array
    {
        return [];
    }
}
