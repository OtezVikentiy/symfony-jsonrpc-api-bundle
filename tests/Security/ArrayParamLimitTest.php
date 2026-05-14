<?php

namespace OV\JsonRPCAPIBundle\Tests\Security;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Logging\NullJsonRpcCallLogger;
use OV\JsonRPCAPIBundle\Core\Request\BaseRequest;
use OV\JsonRPCAPIBundle\Core\Services\HeadersPreparer;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler;
use OV\JsonRPCAPIBundle\Core\Services\ResponseService;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use OV\JsonRPCAPIBundle\RPC\V1\CreateSome\Request;
use OV\JsonRPCAPIBundle\RPC\V1\CreateSome\Token;
use OV\JsonRPCAPIBundle\RPC\V1\CreateSomeMethod;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ArrayParamLimitTest extends TestCase
{
    public function testOversizedArrayIsRejected(): void
    {
        $handler = $this->buildHandler(maxArrayParamSize: 5);
        $methodSpec = $this->createSomeMethodSpec();
        $baseRequest = new BaseRequest($this->payloadWithTokens(10));

        $this->expectException(JRPCException::class);
        $this->expectExceptionCode(JRPCException::INVALID_PARAMS);

        $this->invokeHydrate($handler, new Request(), $methodSpec, $baseRequest);
    }

    public function testArrayAtLimitIsAccepted(): void
    {
        $handler = $this->buildHandler(maxArrayParamSize: 5);
        $methodSpec = $this->createSomeMethodSpec();
        $baseRequest = new BaseRequest($this->payloadWithTokens(5));

        $instance = $this->invokeHydrate($handler, new Request(), $methodSpec, $baseRequest);

        $this->assertCount(5, $instance->getTokens());
    }

    private function payloadWithTokens(int $count): array
    {
        $tokens = [];
        for ($i = 0; $i < $count; $i++) {
            $tokens[] = ['name' => 'n' . $i, 'value' => 'v' . $i, 'summary' => 's' . $i];
        }

        return [
            'jsonrpc' => '2.0',
            'method' => 'CreateSomeMethod',
            'params' => ['tokens' => $tokens],
            'id' => 1,
        ];
    }

    private function createSomeMethodSpec(): MethodSpec
    {
        return new MethodSpec(
            methodClass: CreateSomeMethod::class,
            requestType: 'POST',
            methodName: 'CreateSomeMethod',
            requestMetadata: new RequestMetadata(
                request: Request::class,
                allParameters: [['name' => 'tokens', 'type' => Token::class]],
                requiredParameters: [],
                requestGetters: ['tokens' => 'getTokens'],
                requestSetters: ['tokens' => 'setTokens'],
                requestAdders: ['token' => 'addToken'],
                validators: [],
            ),
            swaggerMetadata: new SwaggerMetadata(
                summary: '',
                description: '',
                ignoreInSwagger: true,
            ),
        );
    }

    private function invokeHydrate(RequestHandler $handler, Request $instance, MethodSpec $spec, BaseRequest $baseRequest): Request
    {
        $ref = new ReflectionMethod(RequestHandler::class, 'hydrateRequest');

        return $ref->invoke($handler, $instance, $spec, $baseRequest);
    }

    private function buildHandler(int $maxArrayParamSize): RequestHandler
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturn(true);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $headersPreparer = new HeadersPreparer(['*']);
        $responseService = new ResponseService($headersPreparer);

        return new RequestHandler(
            $security,
            new MethodSpecCollection(),
            $validator,
            $headersPreparer,
            $this->createMock(Container::class),
            $responseService,
            new NullJsonRpcCallLogger(),
            maxArrayParamSize: $maxArrayParamSize,
        );
    }
}
