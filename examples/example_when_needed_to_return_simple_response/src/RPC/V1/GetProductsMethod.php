<?php

namespace App\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use App\RPC\V1\GetProducts\Request;
use App\RPC\V1\GetProducts\ErrorResponse;
use App\RPC\V1\GetProducts\PlainResponse;

#[JsonRPCAPI(methodName: 'getProducts', type: 'POST')]
class GetProductsMethod
{
    /**
     * @param Request $request // !!!ATTENTION!!! Do not rename this param - just change type, but not the name of variable
     * @return ErrorResponse
     */
    public function call(Request $request): ErrorResponse|PlainResponse
    {
        $rawData = $request->toArray();

        if ($rawData['id'] % 2 === 0) {
            return (new ErrorResponse(false))->addError('Some error text here');
        }

        return new PlainResponse(SomeService::getPicture(), headers: ['Content-type' => 'image/png']);
    }
}