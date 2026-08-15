<?php
/** @noinspection PhpUnused */

/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\RPC\V1\UploadFile;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Written without a constructor on purpose: the file then arrives through the optional-setter path
 * in hydrateRequest(), which is the branch the passthrough rule has to cover. The
 * required-constructor path hands typed values straight to `new`, so it never needed one.
 */
final class UploadFileRequest
{
    private UploadedFile $file;
    private string $title = '';

    public function getFile(): UploadedFile
    {
        return $this->file;
    }

    public function setFile(UploadedFile $file): void
    {
        $this->file = $file;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
