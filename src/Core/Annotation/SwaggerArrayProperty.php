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
final readonly class SwaggerArrayProperty
{
    public function __construct(
        private string $type,
        private bool $ofClass = false,
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isOfClass(): bool
    {
        return $this->ofClass;
    }
}