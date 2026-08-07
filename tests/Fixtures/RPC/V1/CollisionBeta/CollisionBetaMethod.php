<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\RPC\V1\CollisionBeta;

final class CollisionBetaMethod
{
    public function call(): Response
    {
        return new Response(new Filter(1));
    }
}
