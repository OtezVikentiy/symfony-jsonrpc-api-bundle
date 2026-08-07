<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Core\Response;

use Symfony\Component\HttpFoundation\Response;

/**
 * Empty-body response for a CORS `OPTIONS` preflight request.
 */
final class CorsPreflightResponse extends Response implements PlainResponseInterface
{
}
