<?php

namespace OV\JsonRPCAPIBundle\Core;

class BaseRequest
{
    private mixed $jsonrpc;
    private mixed $method;
    private mixed $params;
    private mixed $id;

    public function __construct(
        array $data
    ) {
        if (empty($data['jsonrpc'])) throw new JRPCException('BaseRequest jsonrpc field is absent', JRPCException::INVALID_REQUEST);
        if (empty($data['method'])) throw new JRPCException('BaseRequest method field is absent', JRPCException::INVALID_REQUEST);
        if (empty($data['params'])) throw new JRPCException('BaseRequest params field is absent', JRPCException::INVALID_REQUEST);

        $this->jsonrpc = $data['jsonrpc'];
        $this->method = $data['method'];
        $this->params = $data['params'];
        if (!empty($data['id'])) {
            $this->id = $data['id'];
        }
    }

    /**
     * @return mixed
     */
    public function getJsonrpc(): mixed
    {
        return $this->jsonrpc;
    }

    /**
     * @return mixed
     */
    public function getMethod(): mixed
    {
        return $this->method;
    }

    /**
     * @return mixed
     */
    public function getParams(): mixed
    {
        return $this->params;
    }

    /**
     * @return mixed
     */
    public function getId(): mixed
    {
        return $this->id;
    }
}