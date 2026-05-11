<?php

namespace OV\JsonRPCAPIBundle\Tests\Security;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use OV\JsonRPCAPIBundle\Core\Services\HeadersPreparer;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler\MultiBatchStrategy;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler\SingleBatchStrategy;
use OV\JsonRPCAPIBundle\Core\Services\ResponseService;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class BatchSizeLimitTest extends TestCase
{
    public function testBatchAboveLimitIsRejected(): void
    {
        $handler = $this->buildHandler(maxBatchSize: 3);
        $oversizedBatch = array_fill(0, 4, ['jsonrpc' => '2.0', 'method' => 'noop', 'id' => 1]);

        $response = $handler->applyStrategy(new MultiBatchStrategy(), $oversizedBatch, 1, 'POST');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $payload = json_decode($response->getContent(), true);
        $this->assertSame(JRPCException::INVALID_REQUEST, $payload['error']['code']);
        $this->assertStringContainsString('Batch size 4 exceeds limit 3', $payload['error']['message']);
    }

    public function testBatchAtLimitIsAccepted(): void
    {
        $handler = $this->buildHandler(maxBatchSize: 3);
        $batch = array_fill(0, 3, ['jsonrpc' => '2.0', 'method' => 'noop', 'id' => 1]);

        $response = $handler->applyStrategy(new MultiBatchStrategy(), $batch, 1, 'POST');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertStringNotContainsString('Batch size', (string) $response->getContent());
    }

    public function testSingleRequestIgnoresBatchLimit(): void
    {
        $handler = $this->buildHandler(maxBatchSize: 1);
        $payload = ['jsonrpc' => '2.0', 'method' => 'noop', 'id' => 1];

        $response = $handler->applyStrategy(new SingleBatchStrategy(), $payload, 1, 'POST');

        $this->assertNotNull($response);
    }

    private function buildHandler(int $maxBatchSize): RequestHandler
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
            maxBatchSize: $maxBatchSize,
        );
    }
}
