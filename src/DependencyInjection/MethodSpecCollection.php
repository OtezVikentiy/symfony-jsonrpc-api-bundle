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

use Exception;
use OV\JsonRPCAPIBundle\Core\JRPCException;

final class MethodSpecCollection
{
    private array $methodSpecs = [];

    /**
     * @throws Exception
     */
    public function addMethodSpec(string $version, string $methodName, MethodSpec $methodSpec): void
    {
        if (!empty($this->methodSpecs[$version][$methodName])) {
            throw new Exception(sprintf('Method name %s already in use.', $methodName));
        }

        $this->methodSpecs[$version][$methodName] = $methodSpec;
    }

    /**
     * @throws JRPCException
     */
    public function getMethodSpec(string $version, string $methodName): MethodSpec
    {
        if (!isset($this->methodSpecs[$version][$methodName])) {
            throw new JRPCException('Method not found.', JRPCException::METHOD_NOT_FOUND);
        }

        return $this->methodSpecs[$version][$methodName];
    }

    /**
     * @return MethodSpec[]
     */
    public function getAllMethods(): array
    {
        return $this->methodSpecs;
    }

    /** @noinspection PhpUnused */
    public function getMethodNames(): array
    {
        return array_keys($this->methodSpecs);
    }
}