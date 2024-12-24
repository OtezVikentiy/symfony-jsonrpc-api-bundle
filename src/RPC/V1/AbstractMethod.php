<?php

namespace OV\JsonRPCAPIBundle\RPC\V1;

use App\RPC\V1\GetProductsMethod;
use OV\JsonRPCAPIBundle\Core\CallbacksInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractMethod implements CallbacksInterface
{
    public function getCallbacks(): array
    {
        return [
            TestCallbackMethod::class => ['log'],
            TestMethod::class => ['log'],
        ];
    }

    public function log(string $processorClass, $request) {
        file_put_contents('./tests/_tmp/AfterMethodCallbackTest.log', sprintf('AfterMethodCallbackTest: %s', $request->getTitle()));
    }
}