<?php

namespace OV\JsonRPCAPIBundle\Tests\Core\Response;

use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;
use Symfony\Component\HttpFoundation\JsonResponse as SymfonyJsonResponse;
use PHPUnit\Framework\TestCase;

final class JsonResponseTest extends TestCase
{
    public function testExtendsSymfonyJsonResponse(): void
    {
        $response = new JsonResponse();

        $this->assertInstanceOf(SymfonyJsonResponse::class, $response);
    }

    public function testImplementsOvResponseInterface(): void
    {
        $response = new JsonResponse();

        $this->assertInstanceOf(OvResponseInterface::class, $response);
    }

    public function testWithData(): void
    {
        $data = ['jsonrpc' => '2.0', 'result' => 'ok'];
        $response = new JsonResponse($data);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getContent());
    }

    public function testWithCustomStatus(): void
    {
        $response = new JsonResponse(data: 'Access not allowed', status: 403);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testWithHeaders(): void
    {
        $response = new JsonResponse(data: null, status: 200, headers: ['X-Custom' => 'value']);

        $this->assertEquals('value', $response->headers->get('X-Custom'));
    }

    public function testWithNullData(): void
    {
        $response = new JsonResponse(null);

        $this->assertEquals(200, $response->getStatusCode());
    }
}
