<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Spec;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\RPC\V1\DefaultedParams\DefaultedParamsRequest;
use OV\JsonRPCAPIBundle\RPC\V1\DefaultedParamsMethod;
use OV\JsonRPCAPIBundle\RPC\V1\ObjectCollection\Bag;
use OV\JsonRPCAPIBundle\RPC\V1\ObjectCollection\Item;
use OV\JsonRPCAPIBundle\RPC\V1\ObjectCollection\ObjectCollectionRequest;
use OV\JsonRPCAPIBundle\RPC\V1\ObjectCollectionMethod;
use OV\JsonRPCAPIBundle\RPC\V1\OptionalParams\OptionalParamsRequest;
use OV\JsonRPCAPIBundle\RPC\V1\OptionalParamsMethod;
use OV\JsonRPCAPIBundle\Tests\Controller\AbstractControllerTestCase;

/**
 * Two corners a DTO reaches only when it mixes the by-position pseudo-field with ordinary ones, or
 * holds a collection in something other than a plain array. Both were broken by fixes to neighbours
 * of theirs, which is exactly why they are pinned here.
 */
final class ParamsPseudoFieldTest extends AbstractControllerTestCase
{
    protected bool $useRealValidator = true;

    /**
     * A DTO may declare params as one optional field among others. Deciding to wrap on "the payload
     * has no literal params key" swallowed the whole by-name object into the pseudo-field, so every
     * named field the caller did send came back reported as missing. The question is whether the
     * payload is by-position - section 4.2 says that means an Array - not whether one key is absent.
     */
    public function testByNameCallOmittingTheOptionalParamsFieldStillWorks(): void
    {
        $payload = $this->call(['other' => 'x'], $this->optionalParamsSpec());

        self::assertArrayHasKey('result', $payload, json_encode($payload));
        self::assertSame('x', $payload['result']['other']);
        self::assertSame([], $payload['result']['params']);
    }

    public function testByNameCallSupplyingBothFieldsStillWorks(): void
    {
        $payload = $this->call(['params' => [1, 2], 'other' => 'x'], $this->optionalParamsSpec());

        self::assertSame('x', $payload['result']['other'] ?? null, json_encode($payload));
        self::assertSame([1, 2], $payload['result']['params']);
    }

    /**
     * The wrapping must still not be a way past extra-field rejection: an object whose keys match
     * nothing declared is by-name with unknown fields, not by-position.
     */
    public function testByNameCallWithAnUndeclaredFieldIsStillRejected(): void
    {
        $payload = $this->call(['foo' => 1], $this->optionalParamsSpec());

        self::assertSame(JRPCException::INVALID_PARAMS, $payload['error']['code'] ?? null, json_encode($payload));
    }

    /**
     * A default on the pseudo-field used to beat the payload. `private array $params = []` is how
     * anyone would write the property - it makes the DTO usable before hydration - and CompilerPass
     * records that default, which hydration then preferred over the by-position payload. Every
     * argument was dropped for the empty array with no error and no log: the method simply ran with
     * nothing. A caller sending [1,2,4] must get [1,2,4].
     */
    public function testPositionalParametersSurviveADefaultOnThePseudoField(): void
    {
        $payload = $this->call([1, 2, 4], $this->defaultedParamsSpec());

        self::assertSame(7, $payload['result']['sum'] ?? null, json_encode($payload));
        self::assertSame(3, $payload['result']['count']);
    }

    public function testAbsentParametersStillFallBackToTheDefault(): void
    {
        $payload = $this->call([], $this->defaultedParamsSpec());

        self::assertSame(0, $payload['result']['count'] ?? null, json_encode($payload));
    }

    /**
     * An adder appends, so an empty list leaves the property unset and the empty-collection branch
     * calls the setter to say "none". When the collection lives in an object - a Doctrine
     * ArrayCollection, or any custom type - that setter refuses the empty array, and the branch this
     * replaced caught exactly that. Without the catch, a caller sending [] was told the server had
     * failed.
     */
    public function testEmptyCollectionHeldInAnObjectIsInvalidParamsNotInternalError(): void
    {
        $payload = $this->call(['items' => []], $this->objectCollectionSpec());

        self::assertSame(JRPCException::INVALID_PARAMS, $payload['error']['code'] ?? null, json_encode($payload));
        self::assertStringNotContainsString('Internal error', $payload['error']['message']);
    }

    public function testNonEmptyCollectionHeldInAnObjectStillWorks(): void
    {
        $payload = $this->call(['items' => [['name' => 'a']]], $this->objectCollectionSpec());

        self::assertSame(1, $payload['result']['count'] ?? null, json_encode($payload));
    }

    /**
     * @param array<mixed> $params
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

    private function optionalParamsSpec(): MethodSpec
    {
        return new MethodSpec(
            methodClass: OptionalParamsMethod::class,
            requestType: 'POST',
            methodName: 'optionalParams',
            requestMetadata: new RequestMetadata(
                request: OptionalParamsRequest::class,
                // defaultValue mirrors what CompilerPass emits for a property that has one
                allParameters: [
                    ['name' => 'params', 'type' => 'array', 'defaultValue' => []],
                    ['name' => 'other', 'type' => 'string'],
                ],
                requiredParameters: [],
                requestGetters: ['params' => 'getParams', 'other' => 'getOther'],
                requestSetters: ['params' => 'setParams', 'other' => 'setOther'],
                requestAdders: [],
                validators: [
                    // hasDefaultValue() on the property, so the real CompilerPass marks it optional
                    'params' => ['allowsNull' => true, 'type' => 'array'],
                    'other' => ['allowsNull' => false, 'type' => 'string'],
                ],
            ),
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: true),
        );
    }

    private function defaultedParamsSpec(): MethodSpec
    {
        return new MethodSpec(
            methodClass: DefaultedParamsMethod::class,
            requestType: 'POST',
            methodName: 'defaultedParams',
            requestMetadata: new RequestMetadata(
                request: DefaultedParamsRequest::class,
                allParameters: [['name' => 'params', 'type' => 'array', 'defaultValue' => []]],
                requiredParameters: [],
                requestGetters: ['params' => 'getParams'],
                requestSetters: ['params' => 'setParams'],
                requestAdders: [],
                validators: ['params' => ['allowsNull' => true, 'type' => 'array']],
            ),
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: true),
        );
    }

    private function objectCollectionSpec(): MethodSpec
    {
        return new MethodSpec(
            methodClass: ObjectCollectionMethod::class,
            requestType: 'POST',
            methodName: 'objectCollection',
            requestMetadata: new RequestMetadata(
                request: ObjectCollectionRequest::class,
                allParameters: [['name' => 'items', 'type' => Item::class]],
                requiredParameters: [],
                requestGetters: ['items' => 'getItems'],
                requestSetters: ['items' => 'setItems'],
                requestAdders: ['items' => 'addItem'],
                validators: ['items' => ['allowsNull' => false, 'type' => Bag::class]],
            ),
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: true),
        );
    }
}
