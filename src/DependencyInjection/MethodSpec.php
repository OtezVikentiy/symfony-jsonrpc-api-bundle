<?php

namespace OV\JsonRPCAPIBundle\DependencyInjection;

class MethodSpec
{
    /**
     * @param string $methodClass
     * @param array $allParameters
     * @param array $requiredParameters
     * @param string|null $request
     * @param array $requestSetters
     */
    public function __construct(
        private readonly string $methodClass,
        private readonly array $allParameters,
        private readonly array $requiredParameters,
        private readonly ?string $request,
        private readonly array $requestSetters,
    ) {

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