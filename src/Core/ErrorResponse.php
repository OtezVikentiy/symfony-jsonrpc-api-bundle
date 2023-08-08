<?php

namespace OV\JsonRPCAPIBundle\Core;

use Exception;

class ErrorResponse
{
    public function __construct(
        private readonly int $code,
        private readonly string $message,
        private readonly ?int $id = null,
        private readonly ?string $additionalInfo = null,
    ) {
    }

    /**
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string|null
     */
    public function getAdditionalInfo(): ?string
    {
        return $this->additionalInfo;
    }
}