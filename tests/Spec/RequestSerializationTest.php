<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Spec;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Request\JsonRpcRequest;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class LeakyRequest extends JsonRpcRequest
{
    private string $login = 'alice';

    private string $passwordHash = 'do-not-serialise-me';

    private string $internalToken = 'sk_live_abcdef';

    public function getLogin(): string
    {
        return $this->login;
    }
}

final class PrivateGetterRequest extends JsonRpcRequest
{
    private string $secret = 'do-not-serialise-me';

    private function getSecret(): string
    {
        return $this->secret;
    }
}

final class LinkedRequest extends JsonRpcRequest
{
    public ?LinkedRequest $peer = null;

    public function getPeer(): ?LinkedRequest
    {
        return $this->peer;
    }
}

final class ChildWithOwnShape
{
    public function toArray(): array
    {
        return ['custom_key' => 'val'];
    }
}

final class ParentOfCustomChild extends JsonRpcRequest
{
    private ChildWithOwnShape $child;

    public function __construct()
    {
        $this->child = new ChildWithOwnShape();
    }

    public function getChild(): ChildWithOwnShape
    {
        return $this->child;
    }
}

/**
 * JsonRpcRequest::toArray() is public API, and the documentation points at it for logging a request.
 * It used to read every property straight off the object through Reflection, so a DTO holding a
 * credential handed it over to whatever the application logged; and it kept no record of the objects
 * it had entered, so two DTOs pointing at each other recursed until the process died on a stack
 * overflow - not an exception, so nothing above could turn it into a response.
 */
final class RequestSerializationTest extends TestCase
{
    public function testPropertyWithoutGetterIsNotExported(): void
    {
        $exported = (new LeakyRequest())->toArray();

        self::assertSame(['login' => 'alice'], $exported);
    }

    public function testPropertyWithNonPublicGetterIsNotExported(): void
    {
        self::assertSame([], (new PrivateGetterRequest())->toArray());
    }

    /**
     * Runs isolated: before the fix this did not fail, it killed the PHP process outright, and a
     * crashed worker takes the rest of the suite with it.
     */
    #[RunInSeparateProcess]
    public function testMutuallyReferencingRequestsRaiseAnErrorInsteadOfCrashing(): void
    {
        $first = new LinkedRequest();
        $second = new LinkedRequest();
        $first->peer = $second;
        $second->peer = $first;

        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INTERNAL_ERROR);
        // The depth bound would also stop this, sixty levels later. Asserting the message is what
        // keeps the identity check itself covered - it is the guard that stops a cycle at once.
        $this->expectExceptionMessage('Cyclic reference');

        $first->toArray();
    }

    public function testNestedObjectWithItsOwnToArrayStillDecidesItsOwnShape(): void
    {
        $exported = (new ParentOfCustomChild())->toArray();

        self::assertSame(['child' => ['custom_key' => 'val']], $exported);
    }
}
