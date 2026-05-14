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

interface JsonRpcCallLoggerInterface
{
    /**
     * Logs an incoming JSON-RPC call that has already been parsed from the HTTP body.
     *
     * @param array<mixed, mixed> $rpcCall Decoded JSON-RPC element.
     */
    public function logRequest(array $rpcCall): LoggedRpcCall;

    /**
     * Logs the raw HTTP body when it could not be parsed as JSON-RPC.
     * If the body decodes successfully despite the upstream failure, masking is applied as usual;
     * otherwise the body is replaced with the marker `[unparseable body, N bytes]`.
     */
    public function logRawRequest(string $rawBody): LoggedRpcCall;

    /**
     * Logs an outgoing response. Must be called in pair with logRequest/logRawRequest,
     * passing the scope they returned.
     *
     * When $response is null the call is treated as a notification and the marker
     * `[no response - notification]` is emitted.
     */
    public function logResponse(LoggedRpcCall $call, ?OvResponseInterface $response): void;
}
