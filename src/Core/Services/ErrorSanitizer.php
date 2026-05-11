<?php

namespace OV\JsonRPCAPIBundle\Core\Services;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Sanitizes uncaught Throwables before they reach the JSON-RPC error response.
 *
 * JRPCException is treated as a deliberate API contract message and passed through.
 * Other Throwables get a generic message client-side while the full exception is logged
 * server-side. Set expose_internal_errors=true to bypass sanitization in non-prod.
 */
final readonly class ErrorSanitizer
{
    public function __construct(
        private bool $exposeInternalErrors = false,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function sanitize(Throwable $error): Throwable
    {
        if ($error instanceof JRPCException) {
            return $error;
        }

        $this->logger?->error('Unhandled exception during JSON-RPC method execution', [
            'exception' => $error,
        ]);

        if ($this->exposeInternalErrors) {
            return $error;
        }

        return new JRPCException(
            'Internal error.',
            JRPCException::INTERNAL_ERROR,
        );
    }
}
