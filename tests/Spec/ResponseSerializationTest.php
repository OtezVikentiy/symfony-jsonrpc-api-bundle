<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Spec;

use DateTime;
use DateTimeImmutable;
use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Request\JsonRpcRequest;
use OV\JsonRPCAPIBundle\Core\Response\BaseResponse;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class NoGetterDto
{
    private string $passwordHash = 'super-secret-hash';
}

/**
 * A getter that exists but is not public. Distinct from NoGetterDto: a check for the method's
 * existence alone accepts this one, and calling it raises an Error rather than leaking quietly -
 * so the visibility test is what stands between a private accessor and a fatal on every response.
 */
final class PrivateGetterDto
{
    private string $passwordHash = 'super-secret-hash';

    private function getPasswordHash(): string
    {
        return $this->passwordHash;
    }
}

/**
 * Promoted public properties and no getters at all - the shortest honest way to write a response
 * DTO, and the shape most affected by where the line is drawn.
 */
final class PublicPropertyDto
{
    public function __construct(
        public array $items = ['a'],
        public bool $truncated = false,
    ) {
    }
}

final class MixedVisibilityDto
{
    public string $exposed = 'yes';

    protected string $shielded = 'no';

    private string $hidden = 'no';
}

final class WithGetterDto
{
    private string $passwordHash = 'super-secret-hash';

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }
}

class ParentDto
{
    private string $internalId = 'base-internal-id';

    public function getInternalId(): string
    {
        return $this->internalId;
    }
}

final class ChildDto extends ParentDto
{
    private string $name = 'child-name';

    public function getName(): string
    {
        return $this->name;
    }
}

final class CycleNodeA
{
    private ?CycleNodeB $b = null;

    public function getB(): ?CycleNodeB
    {
        return $this->b;
    }

    public function setB(CycleNodeB $b): void
    {
        $this->b = $b;
    }
}

final class CycleNodeB
{
    private ?CycleNodeA $a = null;

    public function getA(): ?CycleNodeA
    {
        return $this->a;
    }

    public function setA(CycleNodeA $a): void
    {
        $this->a = $a;
    }
}

final class DateRequestFixture extends JsonRpcRequest
{
    public function __construct(
        private readonly DateTime $mutableDate,
        private readonly DateTimeImmutable $immutableDate,
    ) {
    }

    public function getMutableDate(): DateTime
    {
        return $this->mutableDate;
    }

    public function getImmutableDate(): DateTimeImmutable
    {
        return $this->immutableDate;
    }
}

final class ResponseSerializationTest extends TestCase
{
    public function testPropertyWithoutGetterIsOmittedFromResponse(): void
    {
        $response = new BaseResponse(result: new NoGetterDto());

        $this->assertArrayNotHasKey('passwordHash', $response->toArray()['result']);
    }

    public function testPropertyWithNonPublicGetterIsOmittedFromResponse(): void
    {
        $response = new BaseResponse(result: new PrivateGetterDto());

        $this->assertArrayNotHasKey('passwordHash', $response->toArray()['result']);
    }

    /**
     * A public property is the author saying the field is part of the object's surface, in as many
     * words. Requiring a getter on top of that protects nothing - the leak this serialiser exists to
     * stop is a *private* field escaping - and would drop the promoted properties above.
     */
    public function testPublicPropertyWithoutAGetterIsIncludedInResponse(): void
    {
        $result = (new BaseResponse(result: new PublicPropertyDto()))->toArray()['result'];

        self::assertSame(['items' => ['a'], 'truncated' => false], $result);
    }

    public function testOnlyThePublicHalfOfAMixedDtoIsIncluded(): void
    {
        $result = (new BaseResponse(result: new MixedVisibilityDto()))->toArray()['result'];

        self::assertSame(['exposed' => 'yes'], $result, 'protected and private stay in');
    }

    public function testPropertyWithGetterIsIncludedInResponse(): void
    {
        $response = new BaseResponse(result: new WithGetterDto());

        $result = $response->toArray()['result'];

        $this->assertArrayHasKey('passwordHash', $result);
        $this->assertSame('super-secret-hash', $result['passwordHash']);
    }

    public function testPrivateParentPropertyWithPublicGetterIsIncluded(): void
    {
        $response = new BaseResponse(result: new ChildDto());

        $result = $response->toArray()['result'];

        $this->assertArrayHasKey('internalId', $result);
        $this->assertSame('base-internal-id', $result['internalId']);
        $this->assertArrayHasKey('name', $result);
        $this->assertSame('child-name', $result['name']);
    }

    #[RunInSeparateProcess]
    public function testCyclicObjectGraphRaisesInternalError(): void
    {
        $a = new CycleNodeA();
        $b = new CycleNodeB();
        $a->setB($b);
        $b->setA($a);

        $response = new BaseResponse(result: $a);

        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INTERNAL_ERROR);

        $response->toArray();
    }

    public function testResponseDateTimeIsFormattedAsIso8601(): void
    {
        $date = new DateTime('2024-06-15T10:30:00+00:00');
        $response = new BaseResponse(result: $date);

        $this->assertSame($date->format(DATE_ATOM), $response->toArray()['result']);
    }

    public function testResponseDateTimeImmutableIsFormattedAsIso8601(): void
    {
        $date = new DateTimeImmutable('2024-06-15T10:30:00+00:00');
        $response = new BaseResponse(result: $date);

        $this->assertSame($date->format(DATE_ATOM), $response->toArray()['result']);
    }

    public function testRequestDateTimeAndDateTimeImmutableAreFormattedAsIso8601(): void
    {
        $mutable = new DateTime('2024-06-15T10:30:00+00:00');
        $immutable = new DateTimeImmutable('2024-06-15T10:30:00+00:00');
        $request = new DateRequestFixture($mutable, $immutable);

        $result = $request->toArray();

        $this->assertSame($mutable->format(DATE_ATOM), $result['mutableDate']);
        $this->assertSame($immutable->format(DATE_ATOM), $result['immutableDate']);
    }
}
