<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Profiler;

use OV\JsonRPCAPIBundle\Core\Logging\ContextIdGeneratorInterface;
use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcCallLoggerInterface;
use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcCallScopeInterface;
use OV\JsonRPCAPIBundle\Core\Logging\LoggedRpcCall;
use OV\JsonRPCAPIBundle\Core\Logging\LogPayload;
use OV\JsonRPCAPIBundle\Core\Logging\SensitiveDataMaskerInterface;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;
use OV\JsonRPCAPIBundle\Core\Response\PlainResponseInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;
use WeakMap;

/** @internal Debug-only capture independent of PSR-3 logging. Capture failures never affect RPC. */
final class TraceableJsonRpcCallLogger implements JsonRpcCallLoggerInterface, JsonRpcCallScopeInterface, ResetInterface
{
    private const OMITTED = '[payload omitted: capture limit exceeded]';
    private const UNAVAILABLE = '[profiler capture unavailable]';
    private int $nextScopeId = 0;
    private array $scopes = [];
    /** @var WeakMap<LoggedRpcCall, int> */
    private WeakMap $scopeKeys;
    /** @var WeakMap<LoggedRpcCall, LoggedRpcCall> */
    private WeakMap $innerCalls;
    /** @var WeakMap<Request, list<int>> */
    private WeakMap $requestKeys;
    private array $calls = [];

    public function __construct(
        private readonly JsonRpcCallLoggerInterface $inner,
        private readonly SensitiveDataMaskerInterface $masker,
        private readonly ContextIdGeneratorInterface $contextIdGenerator,
        private readonly int $maxBodyLength = 4096,
        private readonly bool $skipPlainResponses = true,
        private readonly int $maxJsonDepth = 64,
        private readonly int $maxPayloadBytes = 1048576,
        private readonly ?RequestStack $requestStack = null,
    ) {
        $this->reset();
    }

    public function beginScope(bool $batch): void
    {
        $this->scopes[] = [
            'batchId' => $batch ? ++$this->nextScopeId : null,
            'request' => $this->requestStack?->getCurrentRequest(),
        ];
    }

    public function endScope(): void
    {
        array_pop($this->scopes);
    }

    public function logRequest(array $rpcCall): LoggedRpcCall
    {
        try {
            $call = $this->inner->logRequest($rpcCall);
        } catch (Throwable) {
            $call = new LoggedRpcCall('', null, microtime(true));
        }
        $call = $this->wrapCall($call);
        try {
            $this->beginCall($call, $rpcCall, $rpcCall['id'] ?? null);
        } catch (Throwable) {
            // The configured masker or context-id generator may fail. Never retain raw data.
        }

        return $call;
    }

    public function logRawRequest(string $rawBody): LoggedRpcCall
    {
        try {
            $call = $this->inner->logRawRequest($rawBody);
        } catch (Throwable) {
            $call = new LoggedRpcCall('', null, microtime(true));
        }
        $call = $this->wrapCall($call);
        try {
            $decoded = strlen($rawBody) <= $this->decodeBudget()
                ? json_decode($rawBody, true, max(1, $this->maxJsonDepth)) : null;
            $request = is_array($decoded) ? $decoded : ['rawBody' => sprintf(LogPayload::MARKER_UNPARSEABLE_BODY_FORMAT, strlen($rawBody))];
            $this->beginCall($call, $request, is_array($decoded) ? ($decoded['id'] ?? null) : null);
        } catch (Throwable) {
        }

        return $call;
    }

    public function logResponse(LoggedRpcCall $call, ?OvResponseInterface $response): void
    {
        try {
            $this->inner->logResponse($this->innerCalls[$call] ?? $call, $response);
        } catch (Throwable) {
        }
        unset($this->innerCalls[$call]);
        $key = $this->scopeKeys[$call] ?? null;
        if ($key === null) {
            return;
        }
        try {
            [$payload, $outcome, $errorCode, $statusCode] = $this->describeResponse($response);
            $this->calls[$key]['response'] = $payload;
            $this->calls[$key]['outcome'] = $outcome;
            $this->calls[$key]['errorCode'] = $errorCode;
            $this->calls[$key]['statusCode'] = $statusCode;
        } catch (Throwable) {
            $this->calls[$key]['response'] = self::UNAVAILABLE;
            $this->calls[$key]['outcome'] = 'unavailable';
        } finally {
            $this->calls[$key]['durationMs'] = (microtime(true) - $call->startedAt) * 1000;
            unset($this->scopeKeys[$call]);
        }
    }

    /** @return list<array<string, mixed>> */
    public function getCalls(): array
    {
        return array_values($this->calls);
    }

    /** @return list<array<string, mixed>> */
    public function getCallsForRequest(Request $request): array
    {
        if ($this->requestStack === null) {
            return $this->getCalls();
        }
        $calls = [];
        foreach ($this->requestKeys[$request] ?? [] as $key) {
            $calls[] = $this->calls[$key];
        }

        return $calls;
    }

    public function reset(): void
    {
        $this->nextScopeId = 0;
        $this->scopes = [];
        $this->scopeKeys = new WeakMap();
        $this->innerCalls = new WeakMap();
        $this->requestKeys = new WeakMap();
        $this->calls = [];
    }

    private function wrapCall(LoggedRpcCall $innerCall): LoggedRpcCall
    {
        $contextId = $innerCall->contextId;
        if ($contextId === '') {
            try {
                $contextId = $this->contextIdGenerator->generate();
            } catch (Throwable) {
                $contextId = 'profiler-context-unavailable';
            }
        }
        $call = new LoggedRpcCall($contextId, $innerCall->method, microtime(true));
        $this->innerCalls[$call] = $innerCall;

        return $call;
    }

    private function beginCall(LoggedRpcCall $call, array $request, mixed $id): void
    {
        $payload = $this->capture($request);
        $contextId = $call->contextId;
        $method = isset($request['method']) && is_string($request['method']) ? $request['method'] : $call->method;
        $key = count($this->calls);
        $currentRequest = $this->requestStack?->getCurrentRequest();
        $scope = $this->scopes === [] ? null : $this->scopes[array_key_last($this->scopes)];
        $this->calls[$key] = [
            'contextId' => substr($contextId, 0, 128),
            'method' => $method === null ? null : substr($method, 0, LogPayload::MAX_METHOD_LENGTH),
            'id' => is_string($id) ? substr($id, 0, 128) : (is_scalar($id) ? $id : null),
            'request' => $payload,
            'response' => null,
            'outcome' => 'pending',
            'errorCode' => null,
            'statusCode' => null,
            'durationMs' => null,
            'batchId' => $scope !== null && $scope['request'] === $currentRequest ? $scope['batchId'] : null,
        ];
        $this->scopeKeys[$call] = $key;
        $request = $this->requestStack?->getCurrentRequest();
        if ($request !== null) {
            $keys = $this->requestKeys[$request] ?? [];
            $keys[] = $key;
            $this->requestKeys[$request] = $keys;
        }
    }

    private function decodeBudget(): int
    {
        return max(1, $this->maxBodyLength > 0 ? min($this->maxBodyLength, $this->maxPayloadBytes) : $this->maxPayloadBytes);
    }

    private function capture(array $data): array|string
    {
        $budget = $this->decodeBudget();
        if (!$this->fits($data, $budget)) {
            return self::OMITTED;
        }
        $masked = $this->masker->mask($data);
        $budget = $this->decodeBudget();

        return $this->fits($masked, $budget) ? $masked : self::OMITTED;
    }

    /** A bounded walk before masking/encoding; never serialize arbitrary objects or recursive arrays. */
    private function fits(mixed $value, int &$budget, int $depth = 0): bool
    {
        if ($depth > $this->maxJsonDepth || --$budget < 0) {
            return false;
        }
        if (is_array($value)) {
            if (count($value) > $budget) {
                return false;
            }
            foreach ($value as $key => $item) {
                if (!$this->fits($key, $budget, $depth + 1) || !$this->fits($item, $budget, $depth + 1)) {
                    return false;
                }
            }

            return true;
        }
        if ($value instanceof UploadedFile) {
            // The masker describes uploads; check the resulting description again before storing it.
            $budget -= 128;

            return $budget >= 0;
        }
        if (is_object($value) || is_resource($value)) {
            return false;
        }
        if (is_string($value) && strlen($value) > $budget) {
            return false;
        }
        $encoded = json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE);
        $budget -= strlen($encoded === false ? '[json-encode-failed]' : $encoded);

        return $budget >= 0;
    }

    /** @return array{mixed, string, int|null, int|null} */
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
        $isHttpError = $statusCode >= 400;
        if ($this->skipPlainResponses && $response instanceof PlainResponseInterface) {
            return [sprintf(LogPayload::MARKER_PLAIN_RESPONSE_FORMAT, strlen($content)), $isHttpError ? 'error' : 'plain', null, $statusCode];
        }
        if (strlen($content) > $this->decodeBudget()) {
            return [self::OMITTED, $isHttpError ? 'error' : 'omitted', null, $statusCode];
        }
        $decoded = json_decode($content, true, max(1, $this->maxJsonDepth));
        if (!is_array($decoded)) {
            return [sprintf(LogPayload::MARKER_NON_JSON_RESPONSE_FORMAT, strlen($content)), $isHttpError ? 'error' : 'plain', null, $statusCode];
        }
        $error = $decoded['error'] ?? null;
        $errorCode = is_array($error) && isset($error['code']) && is_int($error['code']) ? $error['code'] : null;

        return [$this->capture($decoded), $isHttpError || array_key_exists('error', $decoded) ? 'error' : 'result', $errorCode, $statusCode];
    }
}
