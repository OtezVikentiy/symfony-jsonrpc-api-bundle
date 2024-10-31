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
use RuntimeException;

class MethodSpecCollection
{
    private array $methodSpecs = [];

    public function addMethodSpec(string $methodName, MethodSpec $methodSpec): void
    {
        if (!empty($this->methodSpecs[$methodName])) {
            throw new Exception(sprintf('Method name %s already in use.', $methodName));
        }

        $this->methodSpecs[$methodName] = $methodSpec;
    }

    public function getMethodSpec(string $methodName): MethodSpec
    {
        if (!isset($this->methodSpecs[$methodName])) {
            throw new JRPCException('Method not found.', JRPCException::METHOD_NOT_FOUND);
        }

        return $this->methodSpecs[$methodName];
    }

    /**
     * @return MethodSpec[]
     */
    public function getAllMethods(): array
    {
        return $this->methodSpecs;
    }

    public function getMethodNames(): array
    {
        return array_keys($this->methodSpecs);
    }
}