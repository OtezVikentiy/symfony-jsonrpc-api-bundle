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
    /**
     * @param string      $methodClass
     * @param array       $allParameters
     * @param array       $requiredParameters
     * @param string|null $request
     * @param array       $requestSetters
     * @param array       $validators
     */
    public function __construct(
        private readonly string $methodClass,
        private readonly array $allParameters,
        private readonly array $requiredParameters,
        private readonly ?string $request,
        private readonly array $requestSetters,
        private readonly array $validators,
    ) {
    }

    public function getValidators(): array
    {
        return $this->validators;
    }

    /**
     * @return array
     */
    public function getRequestSetters(): array
    {
        return $this->requestSetters;
    }

    /**
     * @return string|null
     */
    public function getRequest(): ?string
    {
        return $this->request;
    }

    /**
     * @return string
     */
    public function getMethodClass(): string
    {
        return $this->methodClass;
    }

    /**
     * @return array
     */
    public function getAllParameters(): array
    {
        return $this->allParameters;
    }

    /**
     * @return array
     */
    public function getRequiredParameters(): array
    {
        return $this->requiredParameters;
    }
}