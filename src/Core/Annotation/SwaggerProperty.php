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

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class SwaggerProperty
{
    public function __construct(
        private ?string $default = null,
        private ?string $format = null,
        private ?string $example = null,
    ) {
    }

    public function getDefault(): ?string
    {
        return $this->default;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function getExample(): ?string
    {
        return $this->example;
    }
}