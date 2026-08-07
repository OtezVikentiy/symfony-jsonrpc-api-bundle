<?php

namespace OV\JsonRPCAPIBundle\Tests\Security;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Logging\DefaultJsonRpcLogFormatter;
use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcCallLogger;
use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcCallLoggerInterface;
use OV\JsonRPCAPIBundle\Core\Logging\NullJsonRpcCallLogger;
use OV\JsonRPCAPIBundle\Core\Logging\SensitiveDataMasker;
use OV\JsonRPCAPIBundle\Core\Logging\UuidContextIdGenerator;
use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use OV\JsonRPCAPIBundle\Core\Services\ErrorSanitizer;
use OV\JsonRPCAPIBundle\Core\Services\HeadersPreparer;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler\MultiBatchStrategy;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler\SingleBatchStrategy;
use OV\JsonRPCAPIBundle\Core\Services\ResponseService;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use OV\JsonRPCAPIBundle\Tests\Fixtures\TestLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ServiceLocator;
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

    public function testOversizedBatchLogsMetadataNotPayload(): void
    {
        $sink = new TestLogger();
        $callLogger = new JsonRpcCallLogger(
            logger: $sink,
            formatter: new DefaultJsonRpcLogFormatter(LogLevel::INFO, LogLevel::INFO, LogLevel::WARNING),
            masker: new SensitiveDataMasker([], '***', new NullLogger()),
            contextIdGenerator: new UuidContextIdGenerator(),
            maxBodyLength: 0,
            skipPlainResponses: true,
        );

        $handler = $this->buildHandler(maxBatchSize: 3, callLogger: $callLogger);
        $marker = 'super-secret-batch-item-marker';
        $oversizedBatch = array_fill(0, 4, ['jsonrpc' => '2.0', 'method' => 'noop', 'params' => $marker, 'id' => 1]);

        $handler->applyStrategy(new MultiBatchStrategy(), $oversizedBatch, 1, 'POST');

        self::assertNotEmpty($sink->records);
        $requestMessage = $sink->records[0]['message'];

        // The rejected batch's own content (each item carries the marker) must never reach the log —
        // only metadata about the rejection (count vs. limit) should.
        self::assertStringNotContainsString($marker, $requestMessage);
        self::assertStringContainsString('batch_rejected', $requestMessage);
        self::assertStringContainsString('"batch_size":4', $requestMessage);
        self::assertStringContainsString('"max_batch_size":3', $requestMessage);
    }

    private function buildHandler(int $maxBatchSize, ?JsonRpcCallLoggerInterface $callLogger = null): RequestHandler
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturn(true);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $headersPreparer = new HeadersPreparer(['*']);
        $responseService = new ResponseService($headersPreparer, new ErrorSanitizer());

        return new RequestHandler(
            $security,
            new MethodSpecCollection(),
            $validator,
            $headersPreparer,
            $this->createMock(ServiceLocator::class),
            $responseService,
            $callLogger ?? new NullJsonRpcCallLogger(),
            maxBatchSize: $maxBatchSize,
        );
    }
}
