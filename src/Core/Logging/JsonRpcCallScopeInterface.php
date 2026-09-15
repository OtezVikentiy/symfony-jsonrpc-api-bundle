<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Core\Logging;

/** @internal Optional observation of the dispatch boundary. Implementations must not throw. */
interface JsonRpcCallScopeInterface
{
    public function beginScope(bool $batch): void;

    public function endScope(): void;
}
