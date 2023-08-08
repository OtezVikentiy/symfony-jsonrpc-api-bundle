<?php

namespace OV\JsonRPCAPIBundle\Core;

use Exception;
use Throwable;

class JRPCException extends Exception
{
    public const PARSE_ERROR = -32700,
        INVALID_REQUEST = -32600,
        METHOD_NOT_FOUND = -32601,
        INVALID_PARAMS = -32602,
        INTERNAL_ERROR = -32603,
        SERVER_ERROR = -32000; //[-32000;-32099] - Server error codes reserved for implementation-defined server-errors.

    /**
     * @param string $message
     * @param int $code
     * @param string $additionalInfo
     * @param Throwable|null $previous
     * @throws Exception
     */
    public function __construct(string $message, int $code, private readonly string $additionalInfo = '', ?Throwable $previous = null)
    {
        if ($code < -32000 && $code > 32099 && !in_array($code, [-32700, -32600, -32601, -32602, -32603]))
            throw new Exception(sprintf('Undefined code %s for JsonRPCAPIException.', $code));

        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string
     */
    public function getAdditionalInfo(): string
    {
        return $this->additionalInfo;
    }
}