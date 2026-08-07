<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Spec;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\RPC\V1\PlainResponse\Request as PlainResponseRequest;
use OV\JsonRPCAPIBundle\RPC\V1\PlainResponseMethod;
use OV\JsonRPCAPIBundle\RPC\V1\Subtract\SubtractRequest;
use OV\JsonRPCAPIBundle\RPC\V1\SubtractMethod;
use OV\JsonRPCAPIBundle\RPC\V1\Sum\SumRequest;
use OV\JsonRPCAPIBundle\RPC\V1\SumMethod;
use OV\JsonRPCAPIBundle\Core\Response\JsonResponse as OvJsonResponse;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler\MultiBatchStrategy;
use OV\JsonRPCAPIBundle\Tests\Controller\AbstractControllerTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

final class BinaryFakeResponse extends Response implements OvResponseInterface
{
    public function __construct(string $content)
    {
        parent::__construct($content);
    }
}

final class BatchResponseIntegrityTest extends AbstractControllerTestCase
{
    public function testBatchResponseCarriesSameCorsHeadersAsSingleCall(): void
    {
        $sumSpec = $this->sumSpec();

        $singleResult = $this->executeControllerTest(
            data: ['jsonrpc' => '2.0', 'method' => 'sum', 'params' => [1, 2], 'id' => '1'],
            methodSpec: $sumSpec,
        );

        $batchResult = $this->executeControllerTest(
            data: [
                ['jsonrpc' => '2.0', 'method' => 'sum', 'params' => [1, 2], 'id' => '1'],
                ['jsonrpc' => '2.0', 'method' => 'subtract', 'params' => [5, 3], 'id' => '2'],
            ],
            methodSpecs: [$this->sumSpec(), $this->subtractSpec()],
        );

        $this->assertInstanceOf(JsonResponse::class, $singleResult);
        $this->assertInstanceOf(JsonResponse::class, $batchResult);
        $this->assertNotNull($singleResult->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame(
            $singleResult->headers->get('Access-Control-Allow-Origin'),
            $batchResult->headers->get('Access-Control-Allow-Origin'),
        );
    }

    public function testPlainResponseInsideBatchBecomesInternalErrorAndRestOfBatchRuns(): void
    {
        $data = [
            ['jsonrpc' => '2.0', 'method' => 'sum', 'params' => [1, 2], 'id' => '1'],
            ['jsonrpc' => '2.0', 'method' => 'plainResponse', 'params' => ['id' => 5], 'id' => 'plain'],
            ['jsonrpc' => '2.0', 'method' => 'subtract', 'params' => [5, 3], 'id' => '2'],
        ];

        $result = $this->executeControllerTest(
            data: $data,
            methodSpecs: [$this->sumSpec(), $this->subtractSpec(), $this->plainResponseSpec()],
        );

        $this->assertInstanceOf(JsonResponse::class, $result);

        $content = json_decode($result->getContent(), true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
        $this->assertCount(3, $content);

        $this->assertSame('1', $content[0]['id']);
        $this->assertArrayHasKey('result', $content[0]);

        $this->assertSame('plain', $content[1]['id']);
        $this->assertArrayHasKey('error', $content[1]);
        $this->assertArrayNotHasKey('result', $content[1]);
        $this->assertSame(-32603, $content[1]['error']['code']);

        $this->assertSame('2', $content[2]['id']);
        $this->assertArrayHasKey('result', $content[2]);
    }

    public function testPlainResponseInsideBatchIsCaughtEvenWhenMethodSpecDoesNotFlagIt(): void
    {
        $data = [
            ['jsonrpc' => '2.0', 'method' => 'sum', 'params' => [1, 2], 'id' => '1'],
            ['jsonrpc' => '2.0', 'method' => 'plainResponse', 'params' => ['id' => 5], 'id' => 'plain'],
            ['jsonrpc' => '2.0', 'method' => 'subtract', 'params' => [5, 3], 'id' => '2'],
        ];

        $result = $this->executeControllerTest(
            data: $data,
            methodSpecs: [$this->sumSpec(), $this->subtractSpec(), $this->plainResponseSpec(plainResponse: false)],
        );

        $this->assertInstanceOf(JsonResponse::class, $result);

        $content = json_decode($result->getContent(), true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
        $this->assertCount(3, $content);

        $this->assertSame('plain', $content[1]['id']);
        $this->assertArrayHasKey('error', $content[1]);
        $this->assertArrayNotHasKey('result', $content[1]);
        $this->assertSame(-32603, $content[1]['error']['code']);
    }

    public function testMultiBatchStrategyFailsLoudlyInsteadOfSilentlyDroppingUnparsableElement(): void
    {
        $strategy = new MultiBatchStrategy();

        $batch = [
            ['jsonrpc' => '2.0', 'method' => 'first', 'id' => '1'],
            ['jsonrpc' => '2.0', 'method' => 'second', 'id' => '2'],
            ['jsonrpc' => '2.0', 'method' => 'third', 'id' => '3'],
        ];

        $callCount = 0;
        $callback = function () use (&$callCount): OvResponseInterface {
            $callCount++;
            if ($callCount === 2) {
                return new BinaryFakeResponse("\xFF\xFE not json");
            }

            return new OvJsonResponse(
                data: json_encode(['jsonrpc' => '2.0', 'result' => $callCount, 'id' => (string) $callCount]),
                json: true,
            );
        };

        $this->expectException(UnexpectedValueException::class);

        $strategy->handleBatch($batch, 1, 'POST', $callback);
    }

    private function sumSpec(): MethodSpec
    {
        return new MethodSpec(
            methodClass: SumMethod::class,
            requestType: 'POST',
            methodName: 'sum',
            requestMetadata: new RequestMetadata(
                request: SumRequest::class,
                allParameters: [['name' => 'params', 'type' => 'array']],
                requiredParameters: [],
                requestGetters: ['params' => 'getParams'],
                requestSetters: ['params' => 'setParams'],
                requestAdders: [],
                validators: ['params' => ['allowsNull' => false, 'type' => 'array']],
            ),
            swaggerMetadata: new SwaggerMetadata(
                summary: '',
                description: '',
                ignoreInSwagger: false,
            ),
        );
    }

    private function subtractSpec(): MethodSpec
    {
        return new MethodSpec(
            methodClass: SubtractMethod::class,
            requestType: 'POST',
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
            swaggerMetadata: new SwaggerMetadata(
                summary: '',
                description: '',
                ignoreInSwagger: false,
            ),
        );
    }

    private function plainResponseSpec(bool $plainResponse = true): MethodSpec
    {
        return new MethodSpec(
            methodClass: PlainResponseMethod::class,
            requestType: 'POST',
            methodName: 'plainResponse',
            requestMetadata: new RequestMetadata(
                request: PlainResponseRequest::class,
                allParameters: [['name' => 'id', 'type' => 'int']],
                requiredParameters: [['name' => 'id', 'type' => 'int']],
                requestGetters: ['id' => 'getId'],
                requestSetters: ['id' => 'setId'],
                requestAdders: [],
                validators: ['id' => ['allowsNull' => false, 'type' => 'int']],
            ),
            swaggerMetadata: new SwaggerMetadata(
                summary: '',
                description: '',
                ignoreInSwagger: false,
            ),
            plainResponse: $plainResponse,
        );
    }
}
