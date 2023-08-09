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
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @Annotation
 * @Target("CLASS")
 */
#[Attribute] class JsonRPCAPI
{
    /**
     * @Required
     *
     * @var string
     */
    #[Required]
    public string $methodName;

    /**
     * @return string
     */
    public function getMethodName(): string
    {
        return $this->methodName;
    }
}