<?php

declare(strict_types=1);
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

final class BaseRequest
{
    private string $jsonrpc;
    private string $method;
    private array $params = [];
    private mixed $id = null;
    private bool $hasId = false;

    /**
     * @throws JRPCException
     */
    public function __construct(array $data)
    {
        if (!isset($data['jsonrpc']) || $data['jsonrpc'] !== '2.0') {
            throw new JRPCException('Invalid Request.', JRPCException::INVALID_REQUEST);
        }
        if (!isset($data['method']) || !is_string($data['method']) || $data['method'] === '') {
            throw new JRPCException('Invalid Request.', JRPCException::INVALID_REQUEST);
        }
        if (array_key_exists('params', $data) && !is_array($data['params'])) {
            throw new JRPCException('Invalid Request.', JRPCException::INVALID_REQUEST);
        }

        $this->jsonrpc = $data['jsonrpc'];
        $this->method = $data['method'];

        if (!empty($data['params'])) {
            $this->params = $data['params'];
        }
        if (array_key_exists('id', $data)) {
            if (!$this->isValidId($data['id'])) {
                throw new JRPCException('Invalid Request.', JRPCException::INVALID_REQUEST);
            }

            $this->hasId = true;
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

    public function getId(): mixed
    {
        return $this->id;
    }

    public function hasId(): bool
    {
        return $this->hasId;
    }

    private function isValidId(mixed $id): bool
    {
        return $id === null || is_string($id) || is_int($id) || is_float($id);
    }
}
