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
final readonly class JsonRPCAPI
{
    public function __construct(
        private string $methodName,
        private string $type,
        private ?int $version = null,
        private string $summary = '',
        private ?array $tags = null,
        private string $description = '',
        private bool $ignoreInSwagger = false,
        private array $roles = [],
    ) {
    }

    public function getMethodName(): string
    {
        return $this->methodName;
    }

    /** @noinspection PhpUnused */
    public function getType(): string
    {
        return $this->type;
    }

    /** @noinspection PhpUnused */
    public function getVersion(): ?int
    {
        return $this->version;
    }

    /** @noinspection PhpUnused */
    public function getSummary(): string
    {
        return $this->summary;
    }

    /** @noinspection PhpUnused */
    public function getTags(): ?array
    {
        return $this->tags;
    }

    /** @noinspection PhpUnused */
    public function getDescription(): string
    {
        return $this->description;
    }

    /** @noinspection PhpUnused */
    public function isIgnoreInSwagger(): bool
    {
        return $this->ignoreInSwagger;
    }

    /** @noinspection PhpUnused */
    public function getRoles(): array
    {
        return $this->roles;
    }
}