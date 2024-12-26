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

final readonly class MethodSpec
{
    public function __construct(
        private string $methodClass,
        private string $requestType,
        private string $summary,
        private string $description,
        private bool $ignoreInSwagger,
        private string $methodName,
        private array $allParameters,
        private array $requiredParameters,
        private ?string $request,
        private array $requestSetters,
        private array $validators,
        private array $roles = [],
        private bool $plainResponse = false,
        private bool $callbacksExists = false,
    ) {
    }

    public function getRequestType(): string
    {
        return $this->requestType;
    }

    /** @noinspection PhpUnused */
    public function getSummary(): string
    {
        return $this->summary;
    }

    /** @noinspection PhpUnused */
    public function getDescription(): string
    {
        return $this->description;
    }

    /** @noinspection PhpUnused */
    public function isIgnoreInSwagger(): bool
    {
        return $this->ignoreInSwagger;
    }

    /** @noinspection PhpUnused */
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