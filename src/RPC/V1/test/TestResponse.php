<?php

namespace OV\JsonRPCAPIBundle\RPC\V1\test;

class TestResponse
{
    private bool $success;
    private string $title;

    /**
     * @param string $title
     * @param bool $success
     */
    public function __construct(string $title, bool $success = true)
    {
        $this->success = $success;
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @param bool $success
     */
    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }
}