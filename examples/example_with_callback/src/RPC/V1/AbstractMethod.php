<?php

namespace App\RPC\V1;

use OV\JsonRPCAPIBundle\Core\CallbacksInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractMethod implements CallbacksInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ){
    }

    public function getCallbacks(): array
    {
        return [
            GetProductsMethod::class => ['log'],
        ];
    }

    public function log($request) {
        $this->logger->emergency('TEST TEST TEST TEST TEST TEST TEST TEST TEST TEST TEST TEST TEST ');
    }
}