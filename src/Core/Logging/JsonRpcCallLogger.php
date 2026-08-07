<?php

declare(strict_types=1);
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\Core\Logging;

use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;
use OV\JsonRPCAPIBundle\Core\Response\PlainResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class JsonRpcCallLogger implements JsonRpcCallLoggerInterface
{
    private const RPC_METHOD_KEY = 'method';
    private const RESPONSE_ERROR_KEY = 'error';
    private const META_KEY_HTTP_STATUS = 'http_status';
    private const EXCEPTION_CONTEXT_KEY = 'exception';

    private const FALLBACK_CONTEXT_ID = '00000000-0000-0000-0000-000000000000';

    private const MARKER_NOTIFICATION = '[no response - notification]';
    private const MARKER_PLAIN_RESPONSE_FORMAT = '[plain response, %d bytes]';
    private const MARKER_NON_JSON_RESPONSE_FORMAT = '[non-json response, %d bytes]';
    private const MARKER_UNPARSEABLE_BODY_FORMAT = '[unparseable body, %d bytes]';
    private const MARKER_JSON_ENCODE_FAILED = '[json-encode-failed]';
    private const MARKER_TRUNCATED_FORMAT = '...[truncated, %d total bytes]';

    private const JSON_ENCODE_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    private const LOG_MESSAGE_RESPONSE_FAILURE = 'JsonRpcCallLogger failed in logResponse';
    private const LOG_MESSAGE_INTERNAL_FAILURE = 'JsonRpcCallLogger internal failure';

    /**
     * Method names are attacker-controlled and read before any request validation runs, so they are
     * bounded independently of max_body_length: a 128-character method name is already generous for
     * any real RPC method, and it keeps a single field from becoming an unbounded log-injection vector.
     */
    private const MAX_METHOD_LENGTH = 128;

    /**
     * Default depth for logRawRequest()'s speculative json_decode when the caller does not supply one,
     * matching Configuration's max_json_depth default so the error-path decode is bounded the same way
     * as the primary request-parsing path even if this class is instantiated outside the bundle's DI.
     */
    private const DEFAULT_MAX_JSON_DEPTH = 64;

    /**
     * Default cap for how many bytes of a rejected raw body logRawRequest() will attempt to decode,
     * matching Configuration's max_payload_bytes default for the same reason as DEFAULT_MAX_JSON_DEPTH.
     */
    private const DEFAULT_MAX_PAYLOAD_BYTES = 1048576;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly JsonRpcLogFormatterInterface $formatter,
        private readonly SensitiveDataMaskerInterface $masker,
        private readonly ContextIdGeneratorInterface $contextIdGenerator,
        private readonly int $maxBodyLength,
        private readonly bool $skipPlainResponses,
        private readonly int $maxJsonDepth = self::DEFAULT_MAX_JSON_DEPTH,
        private readonly int $maxPayloadBytes = self::DEFAULT_MAX_PAYLOAD_BYTES,
    ) {
    }

    public function logRequest(array $rpcCall): LoggedRpcCall
    {
        try {
            $method = $this->extractMethod($rpcCall);

            $call = new LoggedRpcCall(
                contextId: $this->contextIdGenerator->generate(),
                method: $method,
                startedAt: microtime(true),
            );

            $body = $this->encodeBody($this->masker->mask($rpcCall));
            $this->emit(new JsonRpcLogEntry(Direction::Request, $method, $body, $call->contextId));

            return $call;
        } catch (Throwable $e) {
            return $this->fallbackOnFailure($e);
        }
    }

    public function logRawRequest(string $rawBody): LoggedRpcCall
    {
        try {
            $totalLength = strlen($rawBody);
            $boundedBody = $totalLength > $this->maxPayloadBytes
                ? substr($rawBody, 0, $this->maxPayloadBytes)
                : $rawBody;

            $method = null;
            $decoded = json_decode($boundedBody, true, $this->maxJsonDepth);
            if (is_array($decoded)) {
                $method = $this->extractMethod($decoded);
                $body = $this->encodeBody($this->masker->mask($decoded));
            } else {
                $body = sprintf(self::MARKER_UNPARSEABLE_BODY_FORMAT, $totalLength);
            }

            $call = new LoggedRpcCall(
                contextId: $this->contextIdGenerator->generate(),
                method: $method,
                startedAt: microtime(true),
            );

            $this->emit(new JsonRpcLogEntry(Direction::Request, $method, $body, $call->contextId));

            return $call;
        } catch (Throwable $e) {
            return $this->fallbackOnFailure($e);
        }
    }

    public function logResponse(LoggedRpcCall $call, ?OvResponseInterface $response): void
    {
        try {
            [$body, $isErrorResponse] = $this->encodeResponseBody($response);

            $meta = [];
            if ($response !== null) {
                $meta = [self::META_KEY_HTTP_STATUS => $response->getStatusCode()];
            }

            $this->emit(new JsonRpcLogEntry(Direction::Response, $call->method, $body, $call->contextId, $meta, $isErrorResponse));
        } catch (Throwable $e) {
            $this->logger->error(self::LOG_MESSAGE_RESPONSE_FAILURE, [self::EXCEPTION_CONTEXT_KEY => $e]);
        }
    }

    private function extractMethod(array $data): ?string
    {
        if (!isset($data[self::RPC_METHOD_KEY]) || !is_string($data[self::RPC_METHOD_KEY])) {
            return null;
        }

        return substr($data[self::RPC_METHOD_KEY], 0, self::MAX_METHOD_LENGTH);
    }

    /**
     * @return array{0: string, 1: bool} The rendered body and whether it carries a JSON-RPC error member,
     *                                    computed once here so the formatter never has to re-decode it.
     */
    private function encodeResponseBody(?OvResponseInterface $response): array
    {
        if ($response === null) {
            return [self::MARKER_NOTIFICATION, false];
        }

        if ($this->skipPlainResponses && $response instanceof PlainResponseInterface) {
            return [sprintf(self::MARKER_PLAIN_RESPONSE_FORMAT, strlen((string) $response->getContent())), false];
        }

        $content = (string) $response->getContent();
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return [sprintf(self::MARKER_NON_JSON_RESPONSE_FORMAT, strlen($content)), false];
        }

        $isErrorResponse = array_key_exists(self::RESPONSE_ERROR_KEY, $decoded);

        return [$this->encodeBody($this->masker->mask($decoded)), $isErrorResponse];
    }

    private function encodeBody(array $data): string
    {
        $encoded = json_encode($data, self::JSON_ENCODE_FLAGS);
        if ($encoded === false) {
            return self::MARKER_JSON_ENCODE_FAILED;
        }

        if ($this->maxBodyLength > 0 && strlen($encoded) > $this->maxBodyLength) {
            $total = strlen($encoded);
            $encoded = substr($encoded, 0, $this->maxBodyLength) . sprintf(self::MARKER_TRUNCATED_FORMAT, $total);
        }

        return $encoded;
    }

    private function emit(JsonRpcLogEntry $entry): void
    {
        $formatted = $this->formatter->format($entry);
        $this->logger->log($formatted->level, $formatted->message, $formatted->context);
    }

    private function fallbackOnFailure(Throwable $e): LoggedRpcCall
    {
        $this->logger->error(self::LOG_MESSAGE_INTERNAL_FAILURE, [self::EXCEPTION_CONTEXT_KEY => $e]);

        return new LoggedRpcCall(
            contextId: self::FALLBACK_CONTEXT_ID,
            method: null,
            startedAt: microtime(true),
        );
    }
}
