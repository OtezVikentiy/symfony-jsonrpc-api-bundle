<?php
/** @noinspection PhpUnused */

namespace OV\JsonRPCAPIBundle\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\RPC\V1\PlainResponse\ErrorResponse;
use OV\JsonRPCAPIBundle\RPC\V1\PlainResponse\PlainResponse;
use OV\JsonRPCAPIBundle\RPC\V1\PlainResponse\Request;

#[JsonRPCAPI(methodName: 'plainResponse', type: 'POST', ignoreInSwagger: true)]
class PlainResponseMethod
{
    public function call(Request $request): ErrorResponse|PlainResponse
    {
        $rawData = $request->toArray();

        if ($rawData['id'] % 2 === 0) {
            return (new ErrorResponse(false))->addError('Some error text here');
        }

        return new PlainResponse('some picture string', headers: ['Content-type' => 'image/png']);
    }
}