<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Spec;

use InvalidArgumentException;
use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use TypeError;

/**
 * The last few branches of RequestHandler that no request can reach on its own: a sanitiser reading
 * an engine message that does not look the way engine messages look, and the initialisation probe
 * that decides whether a field may be read. Both are private, both are reached through reflection
 * here, and both are the kind of code that only ever runs on a bad day - which is exactly why
 * leaving them unexercised is how a bad day becomes a worse one.
 */
final class HandlerEdgeBranchTest extends TestCase
{
    /**
     * The sanitiser takes the argument position out of the engine's TypeError and rebuilds the rest
     * from the method specification. An exception that carries no position - anything the DTO throws
     * on its own - leaves nothing to rebuild from, and the generic message is what remains. What
     * matters is that it stays generic: the raw text is what carries the server's filesystem paths.
     *
     * @param array<int, array{name: string, type: string}> $requiredParameters
     */
    #[DataProvider('failuresCarryingNoUsablePosition')]
    public function testAFailureWithNoUsablePositionFallsBackToTheGenericMessage(
        \Throwable $failure,
        array $requiredParameters,
    ): void {
        $described = $this->describe($failure, $requiredParameters);

        self::assertSame('One or more parameters have an unexpected type.', $described);
    }

    public static function failuresCarryingNoUsablePosition(): array
    {
        return [
            'an exception the DTO threw itself' => [
                new InvalidArgumentException('id must be positive'),
                [['name' => 'id', 'type' => 'int']],
            ],
            'a position past the declared parameters' => [
                new TypeError('X::__construct(): Argument #4 ($d) must be of type int, string given'),
                [['name' => 'id', 'type' => 'int']],
            ],
            'no parameters declared at all' => [
                new TypeError('X::__construct(): Argument #1 ($a) must be of type int, string given'),
                [],
            ],
        ];
    }

    public function testAFailureCarryingAPositionNamesThatParameter(): void
    {
        $described = $this->describe(
            new TypeError('X::__construct(): Argument #2 ($title) must be of type string, int given, called in /srv/app/vendor/... on line 269'),
            [['name' => 'id', 'type' => 'int'], ['name' => 'title', 'type' => 'string']],
        );

        self::assertSame('[title] - This value should be of type string', $described);
        self::assertStringNotContainsString('/srv/app', $described, 'the path in the engine message must not survive');
    }

    /**
     * The probe guards a getter call against a typed property that was never hydrated. A field the
     * class does not declare cannot be uninitialised, so reading it is safe; a request instance that
     * is not an object has no fields at all.
     */
    #[DataProvider('initialisationCases')]
    public function testFieldInitialisationIsProbedSafely(mixed $instance, string $field, bool $expected): void
    {
        $probe = new ReflectionMethod(RequestHandler::class, 'isFieldInitialised');
        $probe->setAccessible(true);

        self::assertSame($expected, $probe->invoke($this->handler(), $instance, $field));
    }

    public static function initialisationCases(): array
    {
        $partly = new PartlyInitialised();
        $partly->setFilled('x');

        return [
            'a field the class does not declare' => [new PartlyInitialised(), 'nothing_like_it', true],
            'a typed field never assigned' => [new PartlyInitialised(), 'filled', false],
            'a typed field assigned' => [$partly, 'filled', true],
            'not an object at all' => [null, 'filled', false],
            'a scalar where an object belongs' => ['a string', 'filled', false],
        ];
    }

    /**
     * @param array<int, array{name: string, type: string}> $requiredParameters
     */
    private function describe(\Throwable $failure, array $requiredParameters): string
    {
        $method = new ReflectionMethod(RequestHandler::class, 'describeConstructorFailure');
        $method->setAccessible(true);

        return $method->invoke($this->handler(), $failure, $requiredParameters);
    }

    private function handler(): RequestHandler
    {
        return (new \ReflectionClass(RequestHandler::class))->newInstanceWithoutConstructor();
    }
}

final class PartlyInitialised
{
    private string $filled;

    public function getFilled(): string
    {
        return $this->filled;
    }

    public function setFilled(string $filled): void
    {
        $this->filled = $filled;
    }
}
