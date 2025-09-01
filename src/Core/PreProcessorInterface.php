<?php

namespace OV\JsonRPCAPIBundle\Core;

interface PreProcessorInterface
{
    public function getPreProcessors(): array;
}