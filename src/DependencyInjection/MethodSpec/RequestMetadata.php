<?php

namespace OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;

readonly class RequestMetadata
{
    public function __construct(
        private ?string $request,
        private array $allParameters,
        private array $requiredParameters,
        private array $requestGetters,
        private array $requestSetters,
        private array $requestAdders,
        private array $validators,
    ) {
    }

    public function getRequest(): ?string
    {
        return $this->request;
    }

    public function getAllParameters(): array
    {
        return $this->allParameters;
    }

    public function getRequiredParameters(): array
    {
        return $this->requiredParameters;
    }

    public function getRequestGetters(): array
    {
        return $this->requestGetters;
    }

    public function getRequestSetters(): array
    {
        return $this->requestSetters;
    }

    public function getRequestAdders(): array
    {
        return $this->requestAdders;
    }

    public function getValidators(): array
    {
        return $this->validators;
    }
}
