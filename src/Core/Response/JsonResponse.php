<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Core\Response;

use Symfony\Component\HttpFoundation\JsonResponse as SymfonyJsonResponse;

final class JsonResponse extends SymfonyJsonResponse implements OvResponseInterface
{
}