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

final class DefaultJsonRpcLogFormatter implements JsonRpcLogFormatterInterface
{
    private const REQUEST_PREFIX = 'Request';
    private const RESPONSE_PREFIX = 'Response';
    private const UNKNOWN_METHOD = 'unknown';
    private const HTTP_ERROR_THRESHOLD = 400;
    private const MESSAGE_FORMAT = '%s: [%s] %s context_id: %s';
    private const CONTEXT_KEY_METHOD = 'method';
    private const CONTEXT_KEY_CONTEXT_ID = 'context_id';
    private const CONTEXT_KEY_DIRECTION = 'direction';
    private const META_KEY_HTTP_STATUS = 'http_status';

    /**
     * Defensive re-truncation: JsonRpcCallLogger already bounds method to this length before it ever
     * reaches an entry, but this formatter is a public extension point any JsonRpcCallLoggerInterface
     * implementation may hand entries to, so it must not trust the caller to have done so.
     */
    private const MAX_METHOD_LENGTH = 128;

    /**
     * Matches \r, \n, \t and every other C0 control byte plus DEL — the method field is read before any
     * request validation, so an attacker can put ANSI escapes or fake log lines directly into it.
     */
    private const CONTROL_CHAR_PATTERN = '/[\x00-\x1F\x7F]/';

    public function __construct(
        private readonly string $requestLevel,
        private readonly string $responseLevel,
        private readonly string $errorResponseLevel,
    ) {
    }

    public function format(JsonRpcLogEntry $entry): FormattedLogEntry
    {
        $prefix = match ($entry->direction) {
            Direction::Request => self::REQUEST_PREFIX,
            Direction::Response => self::RESPONSE_PREFIX,
        };
        $method = $this->sanitizeMethod($entry->method ?? self::UNKNOWN_METHOD);
        $message = sprintf(self::MESSAGE_FORMAT, $prefix, $method, $entry->body, $entry->contextId);

        $level = $this->resolveLevel($entry);

        return new FormattedLogEntry(
            message: $message,
            context: [
                self::CONTEXT_KEY_METHOD => $method,
                self::CONTEXT_KEY_CONTEXT_ID => $entry->contextId,
                self::CONTEXT_KEY_DIRECTION => $entry->direction->value,
            ],
            level: $level,
        );
    }

    private function sanitizeMethod(string $method): string
    {
        $truncated = substr($method, 0, self::MAX_METHOD_LENGTH);

        return (string) preg_replace_callback(
            self::CONTROL_CHAR_PATTERN,
            static fn (array $matches): string => match ($matches[0]) {
                "\r" => '\\r',
                "\n" => '\\n',
                "\t" => '\\t',
                default => sprintf('\\x%02X', ord($matches[0])),
            },
            $truncated,
        );
    }

    private function resolveLevel(JsonRpcLogEntry $entry): string
    {
        if ($entry->direction === Direction::Request) {
            return $this->requestLevel;
        }

        if ($this->isErrorResponse($entry)) {
            return $this->errorResponseLevel;
        }

        return $this->responseLevel;
    }

    private function isErrorResponse(JsonRpcLogEntry $entry): bool
    {
        $httpStatus = $entry->meta[self::META_KEY_HTTP_STATUS] ?? null;
        if (is_int($httpStatus) && $httpStatus >= self::HTTP_ERROR_THRESHOLD) {
            return true;
        }

        return $entry->isErrorResponse;
    }
}
