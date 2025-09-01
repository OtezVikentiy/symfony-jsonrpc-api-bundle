<?php

namespace OV\JsonRPCAPIBundle\Core;

interface PostProcessorInterface
{
    public function getPostProcessors(): array;
}