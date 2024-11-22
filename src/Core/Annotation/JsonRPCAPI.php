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
        private readonly string $summary = '',
        private readonly string $description = '',
        private readonly bool $ignoreInSwagger = false,
        private readonly array $roles = [],
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

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isIgnoreInSwagger(): bool
    {
        return $this->ignoreInSwagger;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }
}