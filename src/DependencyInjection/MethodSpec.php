<?php
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\DependencyInjection;

class MethodSpec
{
    public function __construct(
        private readonly string $methodClass,
        private readonly string $requestType,
        private readonly string $summary,
        private readonly string $description,
        private readonly bool $ignoreInSwagger,
        private readonly string $methodName,
        private readonly array $allParameters,
        private readonly array $requiredParameters,
        private readonly ?string $request,
        private readonly array $requestSetters,
        private readonly array $validators,
        private readonly array $roles = [],
        private readonly bool $plainResponse = false,
        private readonly bool $callbacksExists = false,
    ) {
    }

    public function getRequestType(): string
    {
        return $this->requestType;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isIgnoreInSwagger(): bool
    {
        return $this->ignoreInSwagger;
    }

    public function getMethodName(): string
    {
        return $this->methodName;
    }

    public function getValidators(): array
    {
        return $this->validators;
    }

    public function getRequestSetters(): array
    {
        return $this->requestSetters;
    }

    public function getRequest(): ?string
    {
        return $this->request;
    }

    public function getMethodClass(): string
    {
        return $this->methodClass;
    }

    public function getAllParameters(): array
    {
        return $this->allParameters;
    }

    public function getRequiredParameters(): array
    {
        return $this->requiredParameters;
    }

    public function isPlainResponse(): bool
    {
        return $this->plainResponse;
    }

    public function isCallbacksExists(): bool
    {
        return $this->callbacksExists;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }
}