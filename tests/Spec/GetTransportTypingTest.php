<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Spec;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Services\RequestRawDataHandler;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\RPC\V1\TypedGet\TypedGetRequest;
use OV\JsonRPCAPIBundle\RPC\V1\TypedGetMethod;
use OV\JsonRPCAPIBundle\Tests\Controller\AbstractControllerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

/**
 * A query string carries no types - PHP parses one into strings, every value of it - so scalars
 * arriving that way are read as the declared type asks. That is not a hole in the strictness
 * introduced in 5.0: a JSON body does carry types, and "42" where 42 belongs stays a client error
 * there. The distinction is whether the caller had any way to say what they meant.
 *
 * Both halves are asserted here, because the value of each depends on the other holding.
 */
final class GetTransportTypingTest extends AbstractControllerTestCase
{
    protected bool $useRealValidator = true;

    #[DataProvider('valuesAQueryStringCanCarry')]
    public function testAScalarFromAQueryStringIsReadAsItsDeclaredType(array $params): void
    {
        $payload = $this->call($params, 'GET');

        self::assertArrayHasKey('result', $payload, json_encode($payload));
    }

    public static function valuesAQueryStringCanCarry(): array
    {
        return [
            'a string' => [['s' => 'x']],
            'an integer' => [['i' => '3']],
            'a negative integer' => [['i' => '-7']],
            'a float' => [['f' => '1.5']],
            'a float in exponent form' => [['f' => '1e3']],
            'a boolean as 1' => [['b' => '1']],
            'a boolean as true' => [['b' => 'true']],
            'a boolean as on' => [['b' => 'on']],
            'a boolean as 0' => [['b' => '0']],
            'every field at once' => [['s' => 'x', 'i' => '3', 'b' => 'true', 'f' => '1.5']],
        ];
    }

    /**
     * Reading is not guessing. A value that is no representation of the declared type is left alone
     * and refused exactly as before - `?id=abc` really is the caller's mistake, and 1.5 is not an
     * integer however forgiving one feels.
     */
    #[DataProvider('valuesThatAreNotRepresentations')]
    public function testAValueThatRepresentsNothingIsStillRefused(array $params, string $type): void
    {
        $payload = $this->call($params, 'GET');

        self::assertSame(JRPCException::INVALID_PARAMS, $payload['error']['code'] ?? null, json_encode($payload));
        self::assertStringContainsString($type, $payload['error']['message']);
    }

    public static function valuesThatAreNotRepresentations(): array
    {
        return [
            'letters for an int' => [['i' => 'abc'], 'int'],
            'a fraction for an int' => [['i' => '1.5'], 'int'],
            'a word for a bool' => [['b' => 'maybe'], 'bool'],
            'letters for a float' => [['f' => 'xyz'], 'float'],
        ];
    }

    /**
     * The other half: over POST the payload is JSON, which has types, so a stringified number is a
     * client error and stays one. If this ever passes, the coercion has leaked out of the transport
     * it belongs to.
     */
    #[DataProvider('stringifiedScalarsInAJsonBody')]
    public function testAStringifiedScalarInAJsonBodyIsStillRefused(array $params, string $type): void
    {
        $payload = $this->call($params, 'POST');

        self::assertSame(JRPCException::INVALID_PARAMS, $payload['error']['code'] ?? null, json_encode($payload));
        self::assertStringContainsString($type, $payload['error']['message']);
    }

    public static function stringifiedScalarsInAJsonBody(): array
    {
        return [
            'an int as a string' => [['i' => '3'], 'int'],
            'a bool as a string' => [['b' => 'true'], 'bool'],
            'a float as a string' => [['f' => '1.5'], 'float'],
        ];
    }

    public function testRealJsonTypesAreStillAcceptedOverPost(): void
    {
        $payload = $this->call(['s' => 'x', 'i' => 3, 'b' => true, 'f' => 1.5], 'POST');

        self::assertArrayHasKey('result', $payload, json_encode($payload));
    }

    /**
     * What the transport itself hands over, before any of the above runs. Kept as the statement of
     * the underlying fact the rest of this file reacts to.
     */
    public function testTheQueryStringItselfYieldsOnlyStrings(): void
    {
        $data = (new RequestRawDataHandler())->prepareData(
            Request::create('/api/v1?jsonrpc=2.0&method=typedGet&id=1&params[i]=3&params[b]=true', 'GET')
        );

        self::assertSame(['i' => '3', 'b' => 'true'], $data['params']);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function call(array $params, string $requestType): array
    {
        $this->setValidateMethodExpectation('any');

        $response = $this->executeControllerTest(
            ['jsonrpc' => '2.0', 'method' => 'typedGet', 'params' => $params, 'id' => 1],
            $this->spec($requestType),
        );

        return json_decode((string) $response->getContent(), true);
    }

    private function spec(string $requestType): MethodSpec
    {
        return new MethodSpec(
            methodClass: TypedGetMethod::class,
            requestType: $requestType,
            methodName: 'typedGet',
            requestMetadata: new RequestMetadata(
                request: TypedGetRequest::class,
                allParameters: [
                    ['name' => 's', 'type' => 'string', 'defaultValue' => ''],
                    ['name' => 'i', 'type' => 'int', 'defaultValue' => 0],
                    ['name' => 'b', 'type' => 'bool', 'defaultValue' => false],
                    ['name' => 'f', 'type' => 'float', 'defaultValue' => 0.0],
                ],
                requiredParameters: [],
                requestGetters: ['s' => 'getS', 'i' => 'getI', 'b' => 'isB', 'f' => 'getF'],
                requestSetters: ['s' => 'setS', 'i' => 'setI', 'b' => 'setB', 'f' => 'setF'],
                requestAdders: [],
                validators: [
                    's' => ['allowsNull' => true, 'type' => 'string'],
                    'i' => ['allowsNull' => true, 'type' => 'int'],
                    'b' => ['allowsNull' => true, 'type' => 'bool'],
                    'f' => ['allowsNull' => true, 'type' => 'float'],
                ],
            ),
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: true),
        );
    }
}
