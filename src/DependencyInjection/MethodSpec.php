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
        private readonly array $allParameters,
        private readonly array $requiredParameters,
        private readonly ?string $request,
        private readonly array $requestSetters,
        private readonly array $validators,
    ) {
    }

    public function getRequestType(): string
    {
        return $this->requestType;
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
}