<?php
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

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly JsonRpcLogFormatterInterface $formatter,
        private readonly SensitiveDataMaskerInterface $masker,
        private readonly ContextIdGeneratorInterface $contextIdGenerator,
        private readonly int $maxBodyLength,
        private readonly bool $skipPlainResponses,
    ) {
    }

    public function logRequest(array $rpcCall): LoggedRpcCall
    {
        try {
            $method = null;
            if (isset($rpcCall[self::RPC_METHOD_KEY]) && is_string($rpcCall[self::RPC_METHOD_KEY])) {
                $method = $rpcCall[self::RPC_METHOD_KEY];
            }

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
            $method = null;
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                if (isset($decoded[self::RPC_METHOD_KEY]) && is_string($decoded[self::RPC_METHOD_KEY])) {
                    $method = $decoded[self::RPC_METHOD_KEY];
                }
                $body = $this->encodeBody($this->masker->mask($decoded));
            } else {
                $body = sprintf(self::MARKER_UNPARSEABLE_BODY_FORMAT, strlen($rawBody));
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
            $body = $this->encodeResponseBody($response);

            $meta = [];
            if ($response !== null) {
                $meta = [self::META_KEY_HTTP_STATUS => $response->getStatusCode()];
            }

            $this->emit(new JsonRpcLogEntry(Direction::Response, $call->method, $body, $call->contextId, $meta));
        } catch (Throwable $e) {
            $this->logger->error(self::LOG_MESSAGE_RESPONSE_FAILURE, [self::EXCEPTION_CONTEXT_KEY => $e]);
        }
    }

    private function encodeResponseBody(?OvResponseInterface $response): string
    {
        if ($response === null) {
            return self::MARKER_NOTIFICATION;
        }

        if ($this->skipPlainResponses && $response instanceof PlainResponseInterface) {
            return sprintf(self::MARKER_PLAIN_RESPONSE_FORMAT, strlen((string) $response->getContent()));
        }

        $content = (string) $response->getContent();
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return sprintf(self::MARKER_NON_JSON_RESPONSE_FORMAT, strlen($content));
        }

        return $this->encodeBody($this->masker->mask($decoded));
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
