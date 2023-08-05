<?php

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