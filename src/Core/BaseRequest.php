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

class BaseRequest
{
    private string $jsonrpc;
    private string $method;
    private array $params;
    private ?int $id = null;

    /**
     * @param array $data
     *
     * @throws JRPCException
     */
    public function __construct(array $data)
    {
        if (empty($data['jsonrpc'])) {
            throw new JRPCException('BaseRequest jsonrpc field is absent', JRPCException::INVALID_REQUEST);
        }
        if (empty($data['method'])) {
            throw new JRPCException('BaseRequest method field is absent', JRPCException::INVALID_REQUEST);
        }
        if (!isset($data['params']) && !is_array($data['params'])) {
            throw new JRPCException('BaseRequest params field is absent', JRPCException::INVALID_REQUEST);
        }

        $this->jsonrpc = $data['jsonrpc'];
        $this->method  = $data['method'];
        $this->params  = $data['params'];
        if (!empty($data['id'])) {
            $this->id = $data['id'];
        }
    }

    /**
     * @return string
     */
    public function getJsonrpc(): string
    {
        return $this->jsonrpc;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }
}