<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Security;

use OV\JsonRPCAPIBundle\Controller\ApiController;
use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcCallLoggerInterface;
use OV\JsonRPCAPIBundle\Core\Logging\NullJsonRpcCallLogger;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;
use OV\JsonRPCAPIBundle\Core\Services\ErrorSanitizer;
use OV\JsonRPCAPIBundle\Core\Services\HeadersPreparer;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler;
use OV\JsonRPCAPIBundle\Core\Services\RequestRawDataHandler;
use OV\JsonRPCAPIBundle\Core\Services\ResponseService;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Exercises `OPTIONS` preflight handling through the real ApiController::index() entry
 * point. A JSON-RPC client sending `Content-Type: application/json` triggers a browser
 * preflight; without a 204 response carrying the CORS headers below, the actual request
 * is never sent.
 */
final class CorsPreflightControllerIntegrationTest extends TestCase
{
    public function testOptionsRequestReturnsNoContentWithFullHeaderSet(): void
    {
        $response = $this->dispatchOptions(['*']);

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame(
            'POST, GET, PUT, PATCH, DELETE',
            $response->headers->get('Access-Control-Allow-Methods'),
        );
        $this->assertNotNull($response->headers->get('Access-Control-Allow-Headers'));
        $this->assertNotNull($response->headers->get('Access-Control-Max-Age'));
    }

    public function testOptionsRequestAllowedHeadersDefaultToContentTypeOnly(): void
    {
        $response = $this->dispatchOptions(['*']);

        $this->assertSame('Content-Type', $response->headers->get('Access-Control-Allow-Headers'));
    }

    public function testOptionsRequestAllowedHeadersReflectConfiguredList(): void
    {
        $response = $this->dispatchOptions(['*'], null, '', ['Content-Type', 'X-AUTH-TOKEN']);

        $this->assertSame('Content-Type, X-AUTH-TOKEN', $response->headers->get('Access-Control-Allow-Headers'));
    }

    public function testOptionsRequestMatchesWhitelistedOrigin(): void
    {
        $response = $this->dispatchOptions(
            ['https://app.example.com', 'https://admin.example.com'],
            'https://admin.example.com',
        );

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertSame('https://admin.example.com', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('Origin', $response->headers->get('Vary'));
    }

    public function testOptionsRequestOmitsAllowOriginForForeignOrigin(): void
    {
        $response = $this->dispatchOptions(['https://app.example.com'], 'https://evil.com');

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
        $this->assertNotNull($response->headers->get('Access-Control-Allow-Methods'));
    }

    public function testOptionsRequestBypassesJsonRpcParsing(): void
    {
        // No body at all — if this reached JSON-RPC parsing it would be an "Invalid Request".
        $response = $this->dispatchOptions(['*'], null, '');

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    private function dispatchOptions(array $originList, ?string $origin = null, string $body = '', ?array $allowedHeaders = null): Response
    {
        $server = [];
        if ($origin !== null) {
            $server['HTTP_ORIGIN'] = $origin;
        }

        $request = Request::create('/api/v1', 'OPTIONS', [], [], [], $server, $body);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $headersPreparer = $allowedHeaders === null
            ? new HeadersPreparer($originList, $requestStack)
            : new HeadersPreparer($originList, $requestStack, $allowedHeaders);
        $responseService = new ResponseService($headersPreparer, new ErrorSanitizer(exposeInternalErrors: false));
        $callLogger = $this->buildCallLogger();

        $requestHandler = new RequestHandler(
            $this->createMock(Security::class),
            new MethodSpecCollection(),
            $this->createMock(ValidatorInterface::class),
            $headersPreparer,
            $this->createMock(Container::class),
            $responseService,
            $callLogger,
        );

        $controller = new ApiController();

        $response = $controller->index(
            $request,
            $requestHandler,
            new RequestRawDataHandler(),
            $responseService,
            $callLogger,
        );

        $this->assertInstanceOf(OvResponseInterface::class, $response);
        $this->assertInstanceOf(Response::class, $response);

        return $response;
    }

    private function buildCallLogger(): JsonRpcCallLoggerInterface
    {
        return new NullJsonRpcCallLogger();
    }
}
