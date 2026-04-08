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

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;

readonly class MethodSpec
{
    public function __construct(
        private string $methodClass,
        private string $requestType,
        private string $methodName,
        private RequestMetadata $requestMetadata,
        private SwaggerMetadata $swaggerMetadata,
        private array $roles = [],
        private bool $plainResponse = false,
        private bool $preProcessorExists = false,
        private bool $postProcessorExists = false,
    ) {
    }

    public function getMethodClass(): string
    {
        return $this->methodClass;
    }

    public function getRequestType(): string
    {
        return $this->requestType;
    }

    /** @noinspection PhpUnused */
    public function getMethodName(): string
    {
        return $this->methodName;
    }

    public function getRequestMetadata(): RequestMetadata
    {
        return $this->requestMetadata;
    }

    public function getSwaggerMetadata(): SwaggerMetadata
    {
        return $this->swaggerMetadata;
    }

    /** @noinspection PhpUnused */
    public function getSummary(): string
    {
        return $this->swaggerMetadata->getSummary();
    }

    /** @noinspection PhpUnused */
    public function getDescription(): string
    {
        return $this->swaggerMetadata->getDescription();
    }

    /** @noinspection PhpUnused */
    public function isIgnoreInSwagger(): bool
    {
        return $this->swaggerMetadata->isIgnoreInSwagger();
    }

    /** @noinspection PhpUnused */
    public function getTags(): ?array
    {
        return $this->swaggerMetadata->getTags();
    }

    public function getGroup(): ?string
    {
        return $this->swaggerMetadata->getGroup();
    }

    public function getRequest(): ?string
    {
        return $this->requestMetadata->getRequest();
    }

    public function getAllParameters(): array
    {
        return $this->requestMetadata->getAllParameters();
    }

    public function getRequiredParameters(): array
    {
        return $this->requestMetadata->getRequiredParameters();
    }

    public function getRequestGetters(): array
    {
        return $this->requestMetadata->getRequestGetters();
    }

    public function getRequestSetters(): array
    {
        return $this->requestMetadata->getRequestSetters();
    }

    public function getRequestAdders(): array
    {
        return $this->requestMetadata->getRequestAdders();
    }

    public function getValidators(): array
    {
        return $this->requestMetadata->getValidators();
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function isPlainResponse(): bool
    {
        return $this->plainResponse;
    }

    public function isPreProcessorExists(): bool
    {
        return $this->preProcessorExists;
    }

    public function isPostProcessorExists(): bool
    {
        return $this->postProcessorExists;
    }
}
