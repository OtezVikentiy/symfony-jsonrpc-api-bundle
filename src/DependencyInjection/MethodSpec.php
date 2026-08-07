<?php

declare(strict_types=1);
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
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal not final: CompilerPass registers this as a lazy service on PHP 8.3+,
 * and Symfony's native lazy ghost proxies require a non-final class to extend.
 */
class MethodSpec
{
    /** @var array<string, Assert\Constraint> */
    private array $compiledValidators;

    public function __construct(
        private readonly string $methodClass,
        private readonly string $requestType,
        private readonly string $methodName,
        private readonly RequestMetadata $requestMetadata,
        private readonly SwaggerMetadata $swaggerMetadata,
        private readonly array $roles = [],
        private readonly bool $plainResponse = false,
        private readonly bool $preProcessorExists = false,
        private readonly bool $postProcessorExists = false,
        private readonly bool $allowExtraFields = false,
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

    /**
     * Symfony constraints are immutable and expensive to construct via reflection,
     * so the compiled set is built once per method spec and reused across requests.
     *
     * @return array<string, Assert\Constraint>
     */
    public function getCompiledValidators(): array
    {
        return $this->compiledValidators ??= $this->compileValidators();
    }

    /**
     * @return array<string, Assert\Constraint>
     */
    private function compileValidators(): array
    {
        $compiled = [];
        foreach ($this->requestMetadata->getValidators() as $field => $validatorItem) {
            $compiled[$field] = $validatorItem['allowsNull'] === false
                ? new Assert\Type($validatorItem['type'])
                : new Assert\Optional([
                    new Assert\AtLeastOneOf([
                        new Assert\Type($validatorItem['type']),
                        new Assert\Blank(),
                        new Assert\IsNull(),
                    ]),
                ]);
        }

        return $compiled;
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

    public function isAllowExtraFields(): bool
    {
        return $this->allowExtraFields;
    }
}
