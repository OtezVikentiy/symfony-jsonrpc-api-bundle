<?php
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\Core\Logging;

use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;

final class NullJsonRpcCallLogger implements JsonRpcCallLoggerInterface
{
    private static ?LoggedRpcCall $emptyScope = null;

    public function logRequest(array $rpcCall): LoggedRpcCall
    {
        return self::emptyScope();
    }

    public function logRawRequest(string $rawBody): LoggedRpcCall
    {
        return self::emptyScope();
    }

    public function logResponse(LoggedRpcCall $call, ?OvResponseInterface $response): void
    {
    }

    private static function emptyScope(): LoggedRpcCall
    {
        return self::$emptyScope ??= new LoggedRpcCall(
            contextId: '',
            method: null,
            startedAt: 0.0,
        );
    }
}
