<?php
/** @noinspection PhpUnused */

namespace OV\JsonRPCAPIBundle\RPC\V1;

use OV\JsonRPCAPIBundle\Core\PostProcessorInterface;
use OV\JsonRPCAPIBundle\Core\PreProcessorInterface;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;

abstract class AbstractMethod implements PreProcessorInterface, PostProcessorInterface
{
    public function getPreProcessors(): array
    {
        return [
            TestPreProcessorMethod::class => ['log'],
            TestMethod::class => ['log'],
        ];
    }
    public function getPostProcessors(): array
    {
        return [
            TestPostProcessorMethod::class => ['log2'],
        ];
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function log(string $processorClass, ?object $request = null): void
    {
        file_put_contents('./tests/_tmp/AfterMethodPreProcessorsTest.log', sprintf('AfterMethodPreProcessorsTest: %s', $request->getTitle()));
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function log2(string $processorClass, ?object $request = null, ?OvResponseInterface $response = null): void
    {
        file_put_contents('./tests/_tmp/AfterMethodPostProcessorsTest.log', sprintf('AfterMethodPostProcessorsTest: %s', $request->getTitle()));
    }
}