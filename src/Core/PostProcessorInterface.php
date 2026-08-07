<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Core;

interface PostProcessorInterface
{
    public function getPostProcessors(): array;
}
