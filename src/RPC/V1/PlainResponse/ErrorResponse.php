<?php
/** @noinspection PhpUnused */

namespace OV\JsonRPCAPIBundle\RPC\V1\PlainResponse;

final class ErrorResponse
{
    private bool $success;
    private array $errors;

    public function __construct(bool $success = true, array $errors = [])
    {
        $this->success = $success;
        $this->errors = $errors;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function setErrors(array $errors): ErrorResponse
    {
        $this->errors = $errors;

        return $this;
    }

    public function addError(string $error): ErrorResponse
    {
        $this->errors[] = $error;

        return $this;
    }
}