<?php
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\Core;

class BaseResponse
{
    /**
     * @param mixed    $result
     * @param int|null $id
     * @param string   $jsonrpc
     */
    public function __construct(
        private readonly mixed $result,
        private readonly ?int $id = null,
        private readonly string $jsonrpc = '2.0'
    ) {
    }

    /**
     * @return string
     */
    public function getJsonrpc(): string
    {
        return $this->jsonrpc;
    }

    /**
     * @return mixed
     */
    public function getResult(): mixed
    {
        return $this->result;
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }
}