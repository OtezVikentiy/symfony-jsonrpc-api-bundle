<?php
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\Core\Response;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use Throwable;

class ErrorResponse implements OvResponseInterface
{
    public function __construct(
        private readonly JRPCException|Throwable $error,
        private readonly ?string $id = null,
        private readonly string $jsonrpc = '2.0',
    ) {
    }

    public function getJsonrpc(): string
    {
        return $this->jsonrpc;
    }

    public function getError(): array
    {
        return [
            'code' => $this->error->getCode(),
            'message' => $this->error->getMessage(),
        ];
    }

    public function getId(): string
    {
        return $this->id;
    }
}