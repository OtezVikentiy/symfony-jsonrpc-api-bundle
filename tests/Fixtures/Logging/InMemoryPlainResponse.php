<?php

namespace OV\JsonRPCAPIBundle\Tests\Fixtures\Logging;

use OV\JsonRPCAPIBundle\Core\Response\PlainResponseInterface;
use Symfony\Component\HttpFoundation\Response;

final class InMemoryPlainResponse extends Response implements PlainResponseInterface
{
    public function __construct(string $content)
    {
        parent::__construct($content);
    }
}
