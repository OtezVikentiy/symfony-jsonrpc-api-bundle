<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Security;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\RPC\V1\CreateSome\Request as CreateSomeRequest;
use OV\JsonRPCAPIBundle\RPC\V1\CreateSome\Token;
use OV\JsonRPCAPIBundle\RPC\V1\CreateSomeMethod;
use OV\JsonRPCAPIBundle\RPC\V1\GetFilteredData;
use OV\JsonRPCAPIBundle\RPC\V1\GetFilteredData\Filter;
use OV\JsonRPCAPIBundle\RPC\V1\GetFilteredData\Request as GetFilteredDataRequest;
use OV\JsonRPCAPIBundle\RPC\V1\Subtract2\Subtract2Request;
use OV\JsonRPCAPIBundle\RPC\V1\Subtract2Method;
use OV\JsonRPCAPIBundle\Tests\Controller\AbstractControllerTestCase;

final class ScalarTypeErrorTest extends AbstractControllerTestCase
{
    public function testNonNumericStringForIntFieldYieldsInvalidParams(): void
    {
        $this->setValidateMethodExpectation('never');

        $response = $this->executeControllerTest(
            [
                'jsonrpc' => '2.0',
                'method' => 'subtract2',
                'params' => ['minuend' => 'abc', 'subtrahend' => 23],
                'id' => 1,
            ],
            $this->spec(),
        );

        $payload = json_decode($response->getContent(), true);

        $this->assertSame(JRPCException::INVALID_PARAMS, $payload['error']['code']);
        $this->assertStringContainsString('minuend', $payload['error']['message']);
        $this->assertStringNotContainsString('Internal error', $payload['error']['message']);
    }

    public function testScalarForNestedDtoSetterYieldsInvalidParams(): void
    {
        $this->setValidateMethodExpectation('never');

        $response = $this->executeControllerTest(
            [
                'jsonrpc' => '2.0',
                'method' => 'GetFilteredData',
                'params' => ['filter' => 5, 'limit' => 2, 'offset' => 0],
                'id' => 1,
            ],
            $this->getFilteredDataSpec(),
        );

        $payload = json_decode($response->getContent(), true);

        $this->assertSame(JRPCException::INVALID_PARAMS, $payload['error']['code']);
        $this->assertStringContainsString('filter', $payload['error']['message']);
        $this->assertStringNotContainsString('Internal error', $payload['error']['message']);
    }

    public function testScalarElementForNestedDtoAdderYieldsInvalidParams(): void
    {
        $this->setValidateMethodExpectation('never');

        $response = $this->executeControllerTest(
            [
                'jsonrpc' => '2.0',
                'method' => 'CreateSomeMethod',
                'params' => ['tokens' => [5]],
                'id' => 1,
            ],
            $this->createSomeSpec(),
        );

        $payload = json_decode($response->getContent(), true);

        $this->assertSame(JRPCException::INVALID_PARAMS, $payload['error']['code']);
        $this->assertStringContainsString('tokens', $payload['error']['message']);
        $this->assertStringNotContainsString('Internal error', $payload['error']['message']);
    }

    private function getFilteredDataSpec(): MethodSpec
    {
        return new MethodSpec(
            methodClass: GetFilteredData::class,
            requestType: 'POST',
            methodName: 'GetFilteredData',
            requestMetadata: new RequestMetadata(
                request: GetFilteredDataRequest::class,
                allParameters: [
                    ['name' => 'filter', 'type' => Filter::class],
                    ['name' => 'limit', 'type' => 'integer'],
                    ['name' => 'offset', 'type' => 'integer'],
                ],
                requiredParameters: [],
                requestGetters: ['filter' => 'getFilter', 'limit' => 'getLimit', 'offset' => 'getOffset'],
                requestSetters: ['filter' => 'setFilter', 'limit' => 'setLimit', 'offset' => 'setOffset'],
                requestAdders: [],
                validators: [],
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
                requestAdders: ['token' => 'addToken'],
                validators: [],
            ),
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: true),
        );
    }

    private function spec(): MethodSpec
    {
        return new MethodSpec(
            methodClass: Subtract2Method::class,
            requestType: 'POST',
            methodName: 'subtract2',
            requestMetadata: new RequestMetadata(
                request: Subtract2Request::class,
                allParameters: [
                    ['name' => 'minuend', 'type' => 'int'],
                    ['name' => 'subtrahend', 'type' => 'int'],
                ],
                requiredParameters: [],
                requestGetters: ['minuend' => 'getMinuend', 'subtrahend' => 'getSubtrahend'],
                requestSetters: ['minuend' => 'setMinuend', 'subtrahend' => 'setSubtrahend'],
                requestAdders: [],
                validators: [],
            ),
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: true),
        );
    }
}
