<?php

namespace OV\JsonRPCAPIBundle\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\Core\PostProcessorInterface;
use OV\JsonRPCAPIBundle\Core\PreProcessorInterface;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;
use OV\JsonRPCAPIBundle\RPC\V1\Handler\BareParamsRequest;

/**
 * Declares its processors under a key that is not this method's own class. The map is keyed by
 * class so a base class can carry processors for several methods, and an entry belonging to another
 * one must be stepped over rather than run - otherwise a shared base would fire every method's
 * hooks on every call.
 */
#[JsonRPCAPI(methodName: 'foreignKeyProcessor', type: 'POST', version: 1, ignoreInSwagger: true)]
final class ForeignKeyProcessorMethod implements PreProcessorInterface, PostProcessorInterface
{
    public static array $ran = [];

    public function getPreProcessors(): array
    {
        return [
            'App\\SomeOtherMethod' => ['mustNotRun'],
            self::class => ['mustRunBefore'],
        ];
    }

    public function getPostProcessors(): array
    {
        return [
            'App\\SomeOtherMethod' => ['mustNotRun'],
            self::class => ['mustRunAfter'],
        ];
    }

    public function mustNotRun(string $class, ?object $request = null, mixed $response = null): void
    {
        self::$ran[] = 'foreign';
    }

    public function mustRunBefore(string $class, ?object $request = null): void
    {
        self::$ran[] = 'before';
    }

    public function mustRunAfter(string $class, ?object $request = null, ?OvResponseInterface $response = null): void
    {
        self::$ran[] = 'after';
    }

    public function call(BareParamsRequest $request): array
    {
        self::$ran[] = 'call';

        return ['ok' => true];
    }
}
