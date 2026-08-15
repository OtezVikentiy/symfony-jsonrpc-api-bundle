<?php
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\RPC\V1\UploadFile;

final class UploadFileResponse
{
    public function __construct(
        private string $originalName = '',
        private string $title = '',
        private int $size = 0,
    ) {
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSize(): int
    {
        return $this->size;
    }
}
