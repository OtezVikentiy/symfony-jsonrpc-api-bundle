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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Exercises Content-Type enforcement through the real ApiController::index() entry point,
 * with a real Symfony Request::create() rather than the mocked Request used by
 * AbstractControllerTestCase. That mocked Request always carries a valid
 * "Content-Type: application/json" header, so the controller test suite built on it can
 * never observe a Content-Type rejection reaching the client.
 */
final class ContentTypeControllerIntegrationTest extends TestCase
{
    private const JSON_BODY = '{"jsonrpc":"2.0","method":"m","id":1}';
    private const INVALID_REQUEST_CODE = -32600;

    public function testFormEncodedBodyIsRejectedEndToEnd(): void
    {
        $request = Request::create(
            '/api/v1',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            'jsonrpc=2.0&method=m&id=1',
        );

        $payload = $this->dispatchAndDecode($request);

        $this->assertSame('2.0', $payload['jsonrpc']);
        $this->assertSame(self::INVALID_REQUEST_CODE, $payload['error']['code']);
        $this->assertNull($payload['id']);
    }

    public function testTextPlainContentTypeWithValidJsonBodyIsRejectedEndToEnd(): void
    {
        $request = Request::create(
            '/api/v1',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            self::JSON_BODY,
        );

        $payload = $this->dispatchAndDecode($request);

        $this->assertSame('2.0', $payload['jsonrpc']);
        $this->assertSame(self::INVALID_REQUEST_CODE, $payload['error']['code']);
        $this->assertNull($payload['id']);
    }

    private function dispatchAndDecode(Request $request): array
    {
        $response = $this->dispatch($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        return json_decode((string) $response->getContent(), true);
    }

    private function dispatch(Request $request): OvResponseInterface
    {
        $headersPreparer = new HeadersPreparer(['*']);
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

        return $controller->index(
            $request,
            $requestHandler,
            new RequestRawDataHandler(),
            $responseService,
            $callLogger,
        );
    }

    private function buildCallLogger(): JsonRpcCallLoggerInterface
    {
        return new NullJsonRpcCallLogger();
    }
}
