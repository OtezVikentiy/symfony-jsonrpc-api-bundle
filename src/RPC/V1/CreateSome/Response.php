<?php
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\RPC\V1\CreateSome;

final class Response
{
    private bool $success;
    private string $responseString;

    public function __construct(bool $success = true)
    {
        $this->success = $success;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): Response
    {
        $this->success = $success;
        return $this;
    }

    public function getResponseString(): string
    {
        return $this->responseString;
    }

    public function setResponseString(string $responseString): Response
    {
        $this->responseString = $responseString;
        return $this;
    }
}