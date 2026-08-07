<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Spec;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\RPC\V1\BareParamsMethod;
use OV\JsonRPCAPIBundle\RPC\V1\ForeignKeyProcessorMethod;
use OV\JsonRPCAPIBundle\RPC\V1\GetCtorMethod;
use OV\JsonRPCAPIBundle\RPC\V1\Handler\BareParamsRequest;
use OV\JsonRPCAPIBundle\RPC\V1\Handler\GetCtorInner;
use OV\JsonRPCAPIBundle\RPC\V1\Handler\GetCtorRequest;
use OV\JsonRPCAPIBundle\RPC\V1\Handler\NullableParamsRequest;
use OV\JsonRPCAPIBundle\RPC\V1\NullableParamsMethod;
use OV\JsonRPCAPIBundle\Tests\Controller\AbstractControllerTestCase;

/**
 * Two things that reach further than the obvious path.
 *
 * Reading a query-string scalar as its declared type has to happen wherever a value meets a typed
 * target, not only at a top-level setter - a constructor argument and a nested DTO's property are
 * both such places, and each is filled by different code.
 *
 * And the by-position pseudo-field is chosen by a chain of conditions whose later links only come
 * up for DTOs written in less usual ways: no default on the property, or a nullable one.
 */
final class PseudoFieldAndCoercionDepthTest extends AbstractControllerTestCase
{
    protected bool $useRealValidator = true;

    public function testAConstructorArgumentIsReadAsItsTypeOverGet(): void
    {
        $payload = $this->call(['id' => '5', 'inner' => ['depth' => '3']], $this->getCtorSpec());

        self::assertSame(5, $payload['result']['id'] ?? null, json_encode($payload));
    }

    public function testANestedDtoPropertyIsReadAsItsTypeOverGet(): void
    {
        $payload = $this->call(['id' => '5', 'inner' => ['depth' => '3']], $this->getCtorSpec());

        self::assertSame(3, $payload['result']['depth'] ?? null, json_encode($payload));
    }

    /**
     * Hydration fills the pseudo-field from the whole payload when the property carries no default -
     * and validation then refuses the call anyway, because a by-name object naming fields the method
     * does not declare is a by-name call with unknown fields, not a by-position one. The two stages
     * disagreeing here is deliberate: hydration is permissive, validation is what answers.
     */
    public function testAByNameObjectIsStillRefusedForAPseudoFieldWithNoDefault(): void
    {
        $payload = $this->call(['a' => 1, 'b' => 2], $this->bareParamsSpec());

        self::assertSame(-32602, $payload['error']['code'] ?? null, json_encode($payload));
        self::assertStringContainsString('not expected', $payload['error']['message']);
    }

    public function testAPseudoFieldWithNoDefaultStillTakesAPositionalPayload(): void
    {
        $payload = $this->call([1, 2, 4], $this->bareParamsSpec());

        self::assertSame([1, 2, 4], $payload['result']['seen'] ?? null, json_encode($payload));
    }

    /**
     * A nullable pseudo-field records null as its default, which hydration must not hand to a setter
     * expecting an array - it falls back to the payload instead. Validation still has the last word
     * on whether the call is acceptable.
     */
    public function testANullablePseudoFieldDoesNotPassItsNullDefaultToTheSetter(): void
    {
        $payload = $this->call(['a' => 1], $this->nullableParamsSpec());

        self::assertSame(-32602, $payload['error']['code'] ?? null, json_encode($payload));
        self::assertStringNotContainsString('Internal error', $payload['error']['message'], 'a null reaching setParams(array) would be -32603');
    }

    public function testANullablePseudoFieldAcceptsAPositionalPayload(): void
    {
        $payload = $this->call([1, 2], $this->nullableParamsSpec());

        self::assertSame([1, 2], $payload['result']['seen'] ?? null, json_encode($payload));
    }

    /**
     * The processor map is keyed by class so a shared base can carry hooks for several methods.
     * An entry keyed to another method must be stepped over - running it would fire every sibling's
     * hooks on every call.
     */
    public function testProcessorsKeyedToAnotherMethodAreNotRun(): void
    {
        ForeignKeyProcessorMethod::$ran = [];

        $this->call([1], $this->foreignKeySpec());

        self::assertSame(['before', 'call', 'after'], ForeignKeyProcessorMethod::$ran);
    }

    private function foreignKeySpec(): MethodSpec
    {
        $spec = $this->paramsSpec(ForeignKeyProcessorMethod::class, 'foreignKeyProcessor', BareParamsRequest::class, false);

        return new MethodSpec(
            methodClass: ForeignKeyProcessorMethod::class,
            requestType: 'POST',
            methodName: 'foreignKeyProcessor',
            requestMetadata: $spec->getRequestMetadata(),
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: true),
            preProcessorExists: true,
            postProcessorExists: true,
        );
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

    private function getCtorSpec(): MethodSpec
    {
        return new MethodSpec(
            methodClass: GetCtorMethod::class,
            requestType: 'GET',
            methodName: 'getCtor',
            requestMetadata: new RequestMetadata(
                request: GetCtorRequest::class,
                allParameters: [['name' => 'id', 'type' => 'int'], ['name' => 'inner', 'type' => GetCtorInner::class]],
                requiredParameters: [['name' => 'id', 'type' => 'int']],
                requestGetters: ['id' => 'getId', 'inner' => 'getInner'],
                requestSetters: ['id' => 'setId', 'inner' => 'setInner'],
                requestAdders: [],
                validators: [
                    'id' => ['allowsNull' => false, 'type' => 'int'],
                    'inner' => ['allowsNull' => false, 'type' => GetCtorInner::class],
                ],
            ),
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: true),
        );
    }

    private function bareParamsSpec(): MethodSpec
    {
        return $this->paramsSpec(BareParamsMethod::class, 'bareParams', BareParamsRequest::class, false);
    }

    private function nullableParamsSpec(): MethodSpec
    {
        return $this->paramsSpec(NullableParamsMethod::class, 'nullableParams', NullableParamsRequest::class, true);
    }

    private function paramsSpec(string $methodClass, string $methodName, string $requestClass, bool $nullable): MethodSpec
    {
        $parameter = ['name' => 'params', 'type' => 'array'];
        if ($nullable) {
            $parameter['defaultValue'] = null;
        }

        return new MethodSpec(
            methodClass: $methodClass,
            requestType: 'POST',
            methodName: $methodName,
            requestMetadata: new RequestMetadata(
                request: $requestClass,
                allParameters: [$parameter],
                requiredParameters: [],
                requestGetters: ['params' => 'getParams'],
                requestSetters: ['params' => 'setParams'],
                requestAdders: [],
                validators: ['params' => ['allowsNull' => $nullable, 'type' => 'array']],
            ),
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: true),
        );
    }
}
