<?php
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\Core\Request;

use OV\JsonRPCAPIBundle\Core\JRPCException;

class BaseRequest
{
    private string $jsonrpc;
    private string $method;
    private array $params = [];
    private mixed $id = null;

    /**
     * @throws JRPCException
     */
    public function __construct(array $data)
    {
        if (
            empty($data['jsonrpc'])
            || empty($data['method'])
            || (!empty($data['params']) && !is_array($data['params']))
        ) {
            throw new JRPCException('Invalid Request.', JRPCException::INVALID_REQUEST);
        }

        $this->jsonrpc = $data['jsonrpc'];
        $this->method  = $data['method'];

        if (!empty($data['params'])) {
            $this->params = $data['params'];
        }
        if (isset($data['id'])) {
            $this->id = $data['id'];
        }
    }

    /** @noinspection PhpUnused */
    public function getJsonrpc(): string
    {
        return $this->jsonrpc;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getId()
    {
        return $this->id;
    }
}