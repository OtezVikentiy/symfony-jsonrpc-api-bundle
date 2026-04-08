<?php

namespace OV\JsonRPCAPIBundle\Core\Response;

interface BaseJsonResponseInterface
{
    public function toArray(): array;
}