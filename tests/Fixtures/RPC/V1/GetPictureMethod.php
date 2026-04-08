<?php
/** @noinspection PhpUnused */

namespace OV\JsonRPCAPIBundle\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\RPC\V1\GetPicture\PlainResponse;
use OV\JsonRPCAPIBundle\RPC\V1\GetPicture\Request;

#[JsonRPCAPI(methodName: 'GetPicture', type: 'POST', ignoreInSwagger: true)]
final class GetPictureMethod
{
    public function call(Request $request): PlainResponse
    {
        return new PlainResponse('some picture string', headers: ['Content-type' => 'image/png']);
    }
}