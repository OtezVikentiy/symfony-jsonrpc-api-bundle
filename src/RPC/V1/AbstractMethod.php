<?php
/** @noinspection PhpUnused */

namespace OV\JsonRPCAPIBundle\RPC\V1;

use OV\JsonRPCAPIBundle\Core\PreProcessorInterface;

abstract class AbstractMethod implements PreProcessorInterface
{
    public function getPreProcessors(): array
    {
        return [
            TestPreProcessorMethod::class => ['log'],
            TestMethod::class => ['log'],
        ];
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function log(string $processorClass, $request): void
    {
        file_put_contents('./tests/_tmp/AfterMethodPreProcessorsTest.log', sprintf('AfterMethodPreProcessorsTest: %s', $request->getTitle()));
    }
}