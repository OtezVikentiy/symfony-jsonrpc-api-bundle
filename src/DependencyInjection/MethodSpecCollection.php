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
use RuntimeException;

class MethodSpecCollection
{
    /**
     * @var MethodSpec[]
     */
    private array $methodSpecs = [];

    /**
     * @param string     $methodName
     * @param MethodSpec $methodSpec
     *
     * @return void
     * @throws Exception
     */
    public function addMethodSpec(string $methodName, MethodSpec $methodSpec): void
    {
        if (!empty($this->methodSpecs[$methodName])) {
            throw new Exception(sprintf('Method name %s already in use.', $methodName));
        }

        $this->methodSpecs[$methodName] = $methodSpec;
    }

    /**
     * @param string $methodName
     *
     * @return MethodSpec
     */
    public function getMethodSpec(string $methodName): MethodSpec
    {
        if (!isset($this->methodSpecs[$methodName])) {
            throw new RuntimeException(
                sprintf(
                    'Method with name %s not found',
                    $methodName
                )
            );
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

    /**
     * @return array
     */
    public function getMethodNames(): array
    {
        return array_keys($this->methodSpecs);
    }
}