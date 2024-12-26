<?php
/** @noinspection PhpUnused */

namespace OV\JsonRPCAPIBundle\RPC\V1;

use OV\JsonRPCAPIBundle\Core\CallbacksInterface;

abstract class AbstractMethod implements CallbacksInterface
{
    public function getCallbacks(): array
    {
        return [
            TestCallbackMethod::class => ['log'],
            TestMethod::class => ['log'],
        ];
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function log(string $processorClass, $request): void
    {
        file_put_contents('./tests/_tmp/AfterMethodCallbackTest.log', sprintf('AfterMethodCallbackTest: %s', $request->getTitle()));
    }
}