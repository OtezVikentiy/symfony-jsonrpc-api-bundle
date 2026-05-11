<?php

namespace OV\JsonRPCAPIBundle\Tests\Core\Services;

use OV\JsonRPCAPIBundle\Core\ApiMethodInterface;
use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Services\ErrorSanitizer;
use OV\JsonRPCAPIBundle\Core\Services\HeadersPreparer;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler;
use OV\JsonRPCAPIBundle\Core\Services\ResponseService;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class InternalErrorTest extends TestCase
{
    public function testUncaughtThrowableBecomesInternalErrorWhenSanitizerEnabled(): void
    {
        $handler = $this->buildHandler(sanitize: true);

        $response = $handler->processBatch([
            'jsonrpc' => '2.0',
            'method' => 'boom',
            'id' => 7,
        ], 1, 'POST');

        $payload = json_decode($response->getContent(), true);
        $this->assertSame(JRPCException::INTERNAL_ERROR, $payload['error']['code']);
        $this->assertSame('Internal error.', $payload['error']['message']);
        $this->assertSame(7, $payload['id']);
        $this->assertStringNotContainsString('secret', $response->getContent());
    }

    public function testCustomJrpcExceptionPassesThroughSanitizer(): void
    {
        $handler = $this->buildHandler(sanitize: true, customError: new JRPCException(
            'Server-side issue.',
            -32001,
            'queue full',
        ));

        $response = $handler->processBatch([
            'jsonrpc' => '2.0',
            'method' => 'boom',
            'id' => 7,
        ], 1, 'POST');

        $payload = json_decode($response->getContent(), true);
        $this->assertSame(-32001, $payload['error']['code']);
        $this->assertStringContainsString('queue full', $payload['error']['message']);
    }

    private function buildHandler(bool $sanitize, ?\Throwable $customError = null): RequestHandler
    {
        $error = $customError ?? new \RuntimeException('connection failed: pass=secret');

        $specCollection = new MethodSpecCollection();
        $methodSpec = new MethodSpec(
            methodClass: ExplodingMethod::class,
            requestType: 'POST',
            methodName: 'boom',
            requestMetadata: new RequestMetadata(
                request: null,
                allParameters: [],
                requiredParameters: [],
                requestGetters: [],
                requestSetters: [],
                requestAdders: [],
                validators: [],
            ),
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: true),
        );
        $specCollection->addMethodSpec(1, 'boom', $methodSpec);

        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturn(true);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $container = $this->createMock(Container::class);
        $container->method('get')->willReturnMap([
            [ExplodingMethod::class, 1, new ExplodingMethod($error)],
        ]);

        $headersPreparer = new HeadersPreparer(['*']);
        $sanitizer = $sanitize ? new ErrorSanitizer(exposeInternalErrors: false) : null;
        $responseService = new ResponseService($headersPreparer, $sanitizer);

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

final class ExplodingMethod implements ApiMethodInterface
{
    public function __construct(private readonly \Throwable $error)
    {
    }

    public function call($request = null): mixed
    {
        throw $this->error;
    }
}
