<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Spec;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\RPC\V1\Handler\CollectionRequest;
use OV\JsonRPCAPIBundle\RPC\V1\Handler\ScalarCollectionRequest;
use OV\JsonRPCAPIBundle\RPC\V1\Handler\StrictCollectionRequest;
use OV\JsonRPCAPIBundle\RPC\V1\Handler\RefusingAdderRequest;
use OV\JsonRPCAPIBundle\RPC\V1\Handler\RequiredCtorCollectionRequest;
use OV\JsonRPCAPIBundle\RPC\V1\Handler\RequiredCtorTag;
use OV\JsonRPCAPIBundle\RPC\V1\Handler\StrictTag;
use OV\JsonRPCAPIBundle\RPC\V1\Handler\Tag;
use OV\JsonRPCAPIBundle\RPC\V1\CollectingMethod;
use OV\JsonRPCAPIBundle\RPC\V1\RefusingAdderMethod;
use OV\JsonRPCAPIBundle\RPC\V1\RequiredCtorCollectingMethod;
use OV\JsonRPCAPIBundle\RPC\V1\ScalarCollectingMethod;
use OV\JsonRPCAPIBundle\RPC\V1\StrictCollectingMethod;
use OV\JsonRPCAPIBundle\Tests\Controller\AbstractControllerTestCase;

/**
 * Filling a collection through an adder has more ways to go wrong than any other part of hydration:
 * the value may not be a list at all, an element may not be shaped like the element type, building
 * one may throw, and the adder itself may refuse what it is handed. Each of those has its own
 * branch and its own message, and none of them was exercised - the suite's collection tests all
 * take the happy path.
 *
 * What matters in every case is the same: a caller's mistake is -32602 naming the field, not -32603
 * and not an exception escaping into the log.
 */
final class CollectionHydrationTest extends AbstractControllerTestCase
{
    protected bool $useRealValidator = true;

    public function testAScalarWhereACollectionBelongsIsRefusedByName(): void
    {
        $payload = $this->call(['tags' => 'not-a-list'], $this->collectionSpec());

        self::assertSame(JRPCException::INVALID_PARAMS, $payload['error']['code'] ?? null, json_encode($payload));
        self::assertStringContainsString('must be an array', $payload['error']['message']);
        self::assertStringContainsString('tags', $payload['error']['message']);
    }

    public function testAnElementThatIsNeitherAnObjectNorAStringIsRefusedByName(): void
    {
        $payload = $this->call(['tags' => [42]], $this->collectionSpec());

        self::assertSame(JRPCException::INVALID_PARAMS, $payload['error']['code'] ?? null, json_encode($payload));
        self::assertStringContainsString('tags', $payload['error']['message']);
    }

    /**
     * The element type refuses the value in its own constructor path. That is still the caller's
     * mistake, so it reads as invalid params rather than as a server failure.
     */
    public function testAnElementTheTypeRefusesToBuildIsInvalidParams(): void
    {
        $payload = $this->call(['tags' => [['name' => 'refuse']]], $this->strictCollectionSpec());

        self::assertSame(JRPCException::INVALID_PARAMS, $payload['error']['code'] ?? null, json_encode($payload));
        self::assertStringNotContainsString('Internal error', $payload['error']['message']);
    }

    /**
     * A collection of scalars hands the raw value to the adder, which may refuse it.
     */
    public function testAScalarElementTheAdderRefusesIsInvalidParams(): void
    {
        $payload = $this->call(['codes' => ['refuse']], $this->scalarCollectionSpec());

        self::assertSame(JRPCException::INVALID_PARAMS, $payload['error']['code'] ?? null, json_encode($payload));
        self::assertStringContainsString('codes', $payload['error']['message']);
    }

    public function testAScalarCollectionIsFilledWhenTheAdderAcceptsIt(): void
    {
        $payload = $this->call(['codes' => ['a', 'b']], $this->scalarCollectionSpec());

        self::assertArrayHasKey('result', $payload, json_encode($payload));
    }

    /**
     * The element type cannot be built at all - its constructor demands an argument the payload
     * fragment does not supply. That is a mismatch between the caller's data and the declared shape,
     * so it reads as invalid params and names the field.
     */
    public function testAnElementTypeThatCannotBeBuiltIsInvalidParams(): void
    {
        $payload = $this->call(['tags' => [['name' => 'a']]], $this->requiredCtorSpec());

        self::assertSame(JRPCException::INVALID_PARAMS, $payload['error']['code'] ?? null, json_encode($payload));
        self::assertStringContainsString('tags', $payload['error']['message']);
        self::assertStringNotContainsString('Internal error', $payload['error']['message']);
    }

    /**
     * The element builds; the collection refuses to take it - a quota, a duplicate check, whatever
     * rule the adder enforces. Still the caller's input, still -32602.
     */
    public function testAnElementTheAdderRefusesIsInvalidParams(): void
    {
        $payload = $this->call(['tags' => [['name' => 'refuse']]], $this->refusingAdderSpec());

        self::assertSame(JRPCException::INVALID_PARAMS, $payload['error']['code'] ?? null, json_encode($payload));
        self::assertStringContainsString('tags', $payload['error']['message']);
    }

    public function testAnElementTheAdderAcceptsIsStored(): void
    {
        $payload = $this->call(['tags' => [['name' => 'fine']]], $this->refusingAdderSpec());

        self::assertSame(1, $payload['result']['count'] ?? null, json_encode($payload));
    }

    /**
     * A partial-update DTO records which fields the caller actually sent, and an empty collection is
     * something the caller sent - "no tags" is a value, not an omission.
     */
    public function testAnEmptyCollectionCountsAsProvidedOnAPartialRequest(): void
    {
        $instance = $this->hydrate(['tags' => []], $this->collectionSpec());

        self::assertTrue($instance->wasProvided('tags'), 'sending [] is not the same as sending nothing');
        self::assertSame([], $instance->getTags());
    }

    public function testAFilledCollectionCountsAsProvidedOnAPartialRequest(): void
    {
        $instance = $this->hydrate(['tags' => [['name' => 'a']]], $this->collectionSpec());

        self::assertTrue($instance->wasProvided('tags'));
        self::assertCount(1, $instance->getTags());
    }

    public function testAnOmittedCollectionIsNotMarkedProvided(): void
    {
        $instance = $this->hydrate([], $this->collectionSpec());

        self::assertFalse($instance->wasProvided('tags'));
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function call(array $params, MethodSpec $spec): array
    {
        $this->setValidateMethodExpectation('any');

        $response = $this->executeControllerTest(
            ['jsonrpc' => '2.0', 'method' => $spec->getMethodName(), 'params' => $params, 'id' => 1],
            $spec,
        );

        return json_decode((string) $response->getContent(), true);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function hydrate(array $params, MethodSpec $spec): CollectionRequest
    {
        CollectingMethod::$last = null;
        $this->call($params, $spec);

        return $this->lastRequestInstance();
    }

    private function lastRequestInstance(): CollectionRequest
    {
        $instance = CollectingMethod::$last;

        self::assertInstanceOf(CollectionRequest::class, $instance, 'the method was never reached');

        return $instance;
    }

    private function collectionSpec(): MethodSpec
    {
        return $this->spec(CollectingMethod::class, 'collecting', CollectionRequest::class, 'tags', Tag::class, 'getTags', 'setTags', 'addTag');
    }

    private function strictCollectionSpec(): MethodSpec
    {
        return $this->spec(StrictCollectingMethod::class, 'strictCollecting', StrictCollectionRequest::class, 'tags', StrictTag::class, 'getTags', 'setTags', 'addTag');
    }

    private function requiredCtorSpec(): MethodSpec
    {
        return $this->spec(RequiredCtorCollectingMethod::class, 'requiredCtorCollecting', RequiredCtorCollectionRequest::class, 'tags', RequiredCtorTag::class, 'getTags', 'setTags', 'addTag');
    }

    private function refusingAdderSpec(): MethodSpec
    {
        return $this->spec(RefusingAdderMethod::class, 'refusingAdder', RefusingAdderRequest::class, 'tags', Tag::class, 'getTags', 'setTags', 'addTag');
    }

    private function scalarCollectionSpec(): MethodSpec
    {
        return $this->spec(ScalarCollectingMethod::class, 'scalarCollecting', ScalarCollectionRequest::class, 'codes', 'string', 'getCodes', 'setCodes', 'addCode');
    }

    private function spec(
        string $methodClass,
        string $methodName,
        string $requestClass,
        string $field,
        string $elementType,
        string $getter,
        string $setter,
        string $adder,
    ): MethodSpec {
        return new MethodSpec(
            methodClass: $methodClass,
            requestType: 'POST',
            methodName: $methodName,
            requestMetadata: new RequestMetadata(
                request: $requestClass,
                // CompilerPass rewrites a collection's declared type to the type of an element
                allParameters: [['name' => $field, 'type' => $elementType, 'defaultValue' => []]],
                requiredParameters: [],
                requestGetters: [$field => $getter],
                requestSetters: [$field => $setter],
                requestAdders: [$field => $adder],
                validators: [$field => ['allowsNull' => true, 'type' => 'array']],
            ),
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: true),
        );
    }
}
