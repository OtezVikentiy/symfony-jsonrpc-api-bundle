<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Spec;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\RPC\V1\CreateSome\Request as CreateSomeRequest;
use OV\JsonRPCAPIBundle\RPC\V1\CreateSome\Token;
use OV\JsonRPCAPIBundle\RPC\V1\CreateSomeMethod;
use OV\JsonRPCAPIBundle\RPC\V1\GetFilteredData;
use OV\JsonRPCAPIBundle\RPC\V1\GetFilteredData\Request as FilteredRequest;
use OV\JsonRPCAPIBundle\Tests\Controller\AbstractControllerTestCase;

/**
 * Section 5.1 separates "the caller sent something wrong" (-32602) from "the server broke"
 * (-32603), and that distinction is the caller's only signal about whether retrying differently
 * could help. Two ordinary inputs landed on the wrong side of it.
 */
final class ParamErrorCodesTest extends AbstractControllerTestCase
{
    protected bool $useRealValidator = true;

    /**
     * Omitting a field typed as a nested DTO left its property uninitialised, and the validation
     * stage read it through its getter anyway - which raises "must not be accessed before
     * initialization", an Error rather than a JRPCException, so the sanitiser turned it into -32603.
     * A caller who simply forgot a field was told the server had failed.
     */
    public function testOmittedObjectFieldIsInvalidParamsRatherThanInternalError(): void
    {
        // The getter is only reached when class_exists($type, false) says the DTO is already loaded,
        // and that check runs without autoloading - so whether this path is taken at all depends on
        // what happened to load first. Loading it here is what makes the test deterministic instead
        // of quietly passing because the class happened to be absent.
        self::assertTrue(class_exists(GetFilteredData\Filter::class), 'precondition: the nested DTO must be loaded');

        $payload = $this->call(
            ['jsonrpc' => '2.0', 'method' => 'GetFilteredData', 'params' => ['limit' => 2, 'offset' => 0], 'id' => 1],
            $this->filteredDataSpec(),
        );

        self::assertSame(JRPCException::INVALID_PARAMS, $payload['error']['code'] ?? null, json_encode($payload));
        self::assertStringNotContainsString('Internal error', $payload['error']['message']);
        self::assertStringContainsString('filter', $payload['error']['message'], 'the caller is entitled to know which field');
    }

    public function testSuppliedObjectFieldStillValidates(): void
    {
        $payload = $this->call(
            [
                'jsonrpc' => '2.0',
                'method' => 'GetFilteredData',
                'params' => ['filter' => ['id' => 1, 'title' => 't', 'finished' => true], 'limit' => 2, 'offset' => 0],
                'id' => 1,
            ],
            $this->filteredDataSpec(),
        );

        self::assertArrayHasKey('result', $payload, json_encode($payload));
    }

    /**
     * An empty list is a legitimate value for a collection - "no items" is an answer, not a mistake.
     * The adder branch was guarded by !empty(), so [] fell through to the setter branch, where
     * CompilerPass has already rewritten the declared type to the type of a single element: the
     * empty array was built into one bare Token and handed to a setter expecting the collection.
     */
    public function testEmptyCollectionIsAccepted(): void
    {
        $payload = $this->call(
            ['jsonrpc' => '2.0', 'method' => 'CreateSomeMethod', 'params' => ['tokens' => []], 'id' => 1],
            $this->createSomeSpec(),
        );

        self::assertArrayHasKey('result', $payload, 'an empty collection must be a usable value: ' . json_encode($payload));
    }

    public function testNonEmptyCollectionStillWorks(): void
    {
        $payload = $this->call(
            [
                'jsonrpc' => '2.0',
                'method' => 'CreateSomeMethod',
                'params' => ['tokens' => [['name' => 'n', 'value' => 'v', 'summary' => 's']]],
                'id' => 1,
            ],
            $this->createSomeSpec(),
        );

        self::assertArrayHasKey('result', $payload, json_encode($payload));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function call(array $data, MethodSpec $spec): array
    {
        $this->setValidateMethodExpectation('any');

        return json_decode((string) $this->executeControllerTest($data, $spec)->getContent(), true);
    }

    private function filteredDataSpec(): MethodSpec
    {
        return new MethodSpec(
            methodClass: GetFilteredData::class,
            requestType: 'POST',
            methodName: 'GetFilteredData',
            requestMetadata: new RequestMetadata(
                request: FilteredRequest::class,
                allParameters: [
                    ['name' => 'filter', 'type' => GetFilteredData\Filter::class],
                    ['name' => 'limit', 'type' => 'integer'],
                    ['name' => 'offset', 'type' => 'integer'],
                ],
                requiredParameters: [],
                requestGetters: ['filter' => 'getFilter', 'limit' => 'getLimit', 'offset' => 'getOffset'],
                requestSetters: ['filter' => 'setFilter', 'limit' => 'setLimit', 'offset' => 'setOffset'],
                requestAdders: [],
                validators: [
                    'filter' => ['allowsNull' => false, 'type' => GetFilteredData\Filter::class],
                    'limit' => ['allowsNull' => false, 'type' => 'integer'],
                    'offset' => ['allowsNull' => false, 'type' => 'integer'],
                ],
            ),
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: true),
        );
    }

    private function createSomeSpec(): MethodSpec
    {
        return new MethodSpec(
            methodClass: CreateSomeMethod::class,
            requestType: 'POST',
            methodName: 'CreateSomeMethod',
            requestMetadata: new RequestMetadata(
                request: CreateSomeRequest::class,
                allParameters: [['name' => 'tokens', 'type' => Token::class]],
                requiredParameters: [],
                requestGetters: ['tokens' => 'getTokens'],
                requestSetters: ['tokens' => 'setTokens'],
                requestAdders: ['tokens' => 'addToken'],
                validators: ['tokens' => ['allowsNull' => false, 'type' => 'array']],
            ),
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: true),
        );
    }
}
