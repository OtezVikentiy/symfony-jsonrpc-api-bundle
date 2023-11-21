<?php
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\Core\Annotation;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class JsonRPCAPI
{
    public function __construct(
        private readonly string $methodName,
        private readonly string $type,
    ) {
    }

    public function getMethodName(): string
    {
        return $this->methodName;
    }

    public function getType(): string
    {
        return $this->type;
    }
}