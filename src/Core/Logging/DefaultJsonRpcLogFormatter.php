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
    private const RESPONSE_ERROR_KEY = 'error';

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
        $method = $entry->method ?? self::UNKNOWN_METHOD;
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

        $decoded = json_decode($entry->body, true);

        return is_array($decoded) && array_key_exists(self::RESPONSE_ERROR_KEY, $decoded);
    }
}
