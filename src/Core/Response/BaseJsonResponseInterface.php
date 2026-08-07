<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Core\Response;

interface BaseJsonResponseInterface
{
    public function toArray(): array;
}
