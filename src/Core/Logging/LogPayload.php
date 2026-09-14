<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Core\Logging;

/** @internal Shared log/profiler payload labels and bounds. */
final class LogPayload
{
    public const MARKER_PLAIN_RESPONSE_FORMAT = '[plain response, %d bytes]';
    public const MARKER_NON_JSON_RESPONSE_FORMAT = '[non-json response, %d bytes]';
    public const MARKER_UNPARSEABLE_BODY_FORMAT = '[unparseable body, %d bytes]';
    /**
     * Method names are attacker-controlled and read before any request validation runs, so they are
     * bounded independently of max_body_length: a 128-character method name is already generous for
     * any real RPC method, and it keeps a single field from becoming an unbounded log-injection vector.
     */
    public const MAX_METHOD_LENGTH = 128;
}
