<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Profiler;

use OV\JsonRPCAPIBundle\Core\Logging\ContextIdGeneratorInterface;
use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcCallLoggerInterface;
use OV\JsonRPCAPIBundle\Core\Logging\LoggedRpcCall;
use OV\JsonRPCAPIBundle\Core\Logging\SensitiveDataMaskerInterface;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Debug-only decorator that keeps profiler data independently of PSR-3 logging.
 *
 * @internal
 */
final class TraceableJsonRpcCallLogger implements JsonRpcCallLoggerInterface, ResetInterface
{
    private const MAX_METHOD_LENGTH = 128;

    private int $nextCallId = 0;

    /**  array<int, int> */
    private array $scopeKeys = [];

    /** @var array<int, LoggedRpcCall> */
    private array $innerCalls = [];

    /** @var array<int, array<string, mixed>> */
    private array $calls = [];

    public function __construct(
        private readonly JsonRpcCallLoggerInterface $inner,
        private readonly SensitiveDataMaskerInterface $masker,
        private readonly ContextIdGeneratorInterface $contextIdGenerator,
    ) {
    }

    public function logRequest(array $rpcCall): LoggedRpcCall
    {
        $startedAt = microtime(true);
        $innerCall = $this->inner->logRequest($rpcCall);
        $method = isset($rpcCall['method']) && is_string($rpcCall['method'])
            ? substr($rpcCall['method'], 0, self::MAX_METHOD_LENGTH)
            : $innerCall->method;
        $call = new LoggedRpcCall(
            contextId: $innerCall->contextId !== '' ? $innerCall->contextId : $this->contextIdGenerator->generate(),
            method: $method,
            startedAt: $startedAt,
        );

        $this->beginCall($call, $innerCall, $this->masker->mask($rpcCall), $rpcCall['id'] ?? null, false);

        return $call;
    }

    public function logRawRequest(string $rawBody): LoggedRpcCall
    {
        $startedAt = microtime(true);
        $innerCall = $this->inner->logRawRequest($rawBody);
        $call = new LoggedRpcCall(
            contextId: $innerCall->contextId !== '' ? $innerCall->contextId : $this->contextIdGenerator->generate(),
            method: $innerCall->method,
            startedAt: $startedAt,
        );

        $this->beginCall(
            $call,
            $innerCall,
            ['rawBody' => sprintf('[unparseable body, %d bytes]', strlen($rawBody))],
            null,
            true,
        );

        return $call;
    }

    public function logResponse(LoggedRpcCall $call, ?OvResponseInterface $response): void
    {
        $scopeId = spl_object_id($call);
        $key = $this->scopeKeys[$scopeId] ?? null;
        $innerCall = $key !== null ? ($this->innerCalls[$key] ?? $call) : $call;

        try {
            $this->inner->logResponse($innerCall, $response);
        } finally {
            if ($key !== null && isset($this->calls[$key])) {
                $finishedAt = microtime(true);
                [$payload, $outcome, $errorCode, $statusCode] = $this->describeResponse($response);
                $this->calls[$key]['response'] = $payload;
                $this->calls[$key]['outcome'] = $outcome;
                $this->calls[$key]['errorCode'] = $errorCode;
                $this->calls[$key]['statusCode'] = $statusCode;
                $this->calls[$key]['durationMs'] = ($finishedAt - $call->startedAt) * 1000;
                $this->calls[$key]['finishedAt'] = $finishedAt;
                unset($this->scopeKeys[$scopeId], $this->innerCalls[$key]);
            }
        }
    }

    /** @return list<array<string, mixed>> */
    public function getCalls(): array
    {
        return array_values($this->calls);
    }

    public function reset(): void
    {
        $this->nextCallId = 0;
        $this->scopeKeys = [];
        $this->innerCalls = [];
        $this->calls = [];
    }

    /** @param array<mixed, mixed> $request */
    private function beginCall(
        LoggedRpcCall $call,
        LoggedRpcCall $innerCall,
        array $request,
        mixed $id,
        bool $raw,
    ): void {
        $key = $this->nextCallId++;
        $this->scopeKeys[spl_object_id($call)] = $key;
        $this->innerCalls[$key] = $innerCall;
        $this->calls[$key] = [
            'contextId' => $call->contextId,
            'method' => $call->method,
            'id' => is_scalar($id) || $id === null ? $id : null,
            'request' => $request,
            'response' => null,
            'outcome' => 'pending',
            'errorCode' => null,
            'statusCode' => null,
            'durationMs' => null,
            'startedAt' => $call->startedAt,
            'finishedAt' => null,
            'raw' => $raw,
        ];
    }

    /** @return array{0: mixed, 1: string, 2: int|null, 3: int|null} */
    private function describeResponse(?OvResponseInterface $response): array
    {
        if ($response === null) {
            return [null, 'notification', null, null];
        }

        if (!$response instanceof Response) {
            return [sprintf('[%s]', $response::class), 'response', null, null];
        }

        $statusCode = $response->getStatusCode();
        $content = (string) $response->getContent();
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return [sprintf('[non-json response, %d bytes]', strlen($content)), 'plain', null, $statusCode];
        }

        $masked = $this->masker->mask($decoded);
        $error = $decoded['error'] ?? null;
        $errorCode = is_array($error) && isset($error['code']) && is_int($error['code'])
            ? $error['code']
            : null;

        return [$masked, array_key_exists('error', $decoded) ? 'error' : 'result', $errorCode, $statusCode];
    }
}
