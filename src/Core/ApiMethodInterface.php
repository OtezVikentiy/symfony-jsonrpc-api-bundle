<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Core;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('ov.rpc.method')]
interface ApiMethodInterface
{
}