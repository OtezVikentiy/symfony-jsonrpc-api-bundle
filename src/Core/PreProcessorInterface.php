<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Core;

interface PreProcessorInterface
{
    public function getPreProcessors(): array;
}
