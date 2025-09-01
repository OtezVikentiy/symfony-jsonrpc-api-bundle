<?php
/** @noinspection PhpUnused */

/** @noinspection PhpUnused */

/** @noinspection PhpUnused */

/** @noinspection PhpUnused */

/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\RPC\V1\TestPreProcessor;

final class Response
{
    private bool $success;
    private string $title;

    public function __construct(string $title, bool $success = true)
    {
        $this->success = $success;
        $this->title   = $title;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }
}