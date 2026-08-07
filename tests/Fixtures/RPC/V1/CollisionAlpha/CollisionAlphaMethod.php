<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\RPC\V1\CollisionAlpha;

final class CollisionAlphaMethod
{
    public function call(): Response
    {
        return new Response(new Filter('alpha'));
    }
}
