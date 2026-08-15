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

namespace OV\JsonRPCAPIBundle\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\RPC\V1\UploadFile\UploadFileRequest;
use OV\JsonRPCAPIBundle\RPC\V1\UploadFile\UploadFileResponse;

#[JsonRPCAPI(
    methodName: 'uploadFile',
    type: 'POST',
    version: 1,
    summary: 'Upload file summary',
    tags: ['upload'],
    description: 'Upload file description',
    acceptsMultipart: true,
)]
final class UploadFileMethod
{
    public function call(UploadFileRequest $request): UploadFileResponse
    {
        $file = $request->getFile();

        return new UploadFileResponse(
            $file->getClientOriginalName(),
            $request->getTitle(),
            (int) $file->getSize(),
        );
    }
}
