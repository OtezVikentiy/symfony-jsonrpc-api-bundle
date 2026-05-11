<?php

namespace OV\JsonRPCAPIBundle\Tests\Core\Services;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Services\HeadersPreparer;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler;
use OV\JsonRPCAPIBundle\Core\Services\ResponseService;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use OV\JsonRPCAPIBundle\RPC\V1\Subtract\SubtractRequest;
use OV\JsonRPCAPIBundle\RPC\V1\SubtractMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class HttpMethodEnforcementTest extends TestCase
{
    public function testPostRequestRejectedWhenMethodRequiresPut(): void
    {
        $handler = $this->buildHandler('PUT');

        $response = $handler->processBatch([
            'jsonrpc' => '2.0',
            'method' => 'subtract',
            'params' => [10, 5],
            'id' => 1,
        ], 1, 'POST');

        $this->assertNotNull($response);
        $payload = json_decode($response->getContent(), true);
        $this->assertSame(JRPCException::INVALID_REQUEST, $payload['error']['code']);
    }

    public function testHappyPathWhenMethodTypeMatches(): void
    {
        $handler = $this->buildHandler('POST');

        $response = $handler->processBatch([
            'jsonrpc' => '2.0',
            'method' => 'subtract',
            'params' => [10, 5],
            'id' => 1,
        ], 1, 'POST');

        $this->assertNotNull($response);
        $payload = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('result', $payload);
    }

    private function buildHandler(string $expectedHttpMethod): RequestHandler
    {
        $specCollection = new MethodSpecCollection();
        $methodSpec = new MethodSpec(
            methodClass: SubtractMethod::class,
            requestType: $expectedHttpMethod,
            methodName: 'subtract',
            requestMetadata: new RequestMetadata(
                request: SubtractRequest::class,
                allParameters: [['name' => 'params', 'type' => 'array']],
                requiredParameters: [],
                requestGetters: ['params' => 'getParams'],
                requestSetters: ['params' => 'setParams'],
                requestAdders: [],
                validators: ['params' => ['allowsNull' => false, 'type' => 'array']],
            ),
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: true),
        );
        $specCollection->addMethodSpec(1, 'subtract', $methodSpec);

        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturn(true);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $container = $this->createMock(Container::class);
        $container->method('get')->willReturnMap([
            [SubtractMethod::class, 1, new SubtractMethod()],
        ]);

        $headersPreparer = new HeadersPreparer(['*']);
        $responseService = new ResponseService($headersPreparer);

        return new RequestHandler(
            $security,
            $specCollection,
            $validator,
            $headersPreparer,
            $container,
            $responseService,
        );
    }
}
