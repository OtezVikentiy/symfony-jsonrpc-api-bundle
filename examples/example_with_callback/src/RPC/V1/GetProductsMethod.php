<?php

namespace App\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use App\RPC\V1\GetProducts\GetProductsRequest;
use App\RPC\V1\GetProducts\GetProductsResponse;

#[JsonRPCAPI(methodName: 'getProducts', type: 'POST')]
class GetProductsMethod extends AbstractMethod
{
    /**
     * @param GetProductsRequest $request // !!!ATTENTION!!! Do not rename this param - just change type, but not the name of variable
     * @return GetProductsResponse
     */
    public function call(GetProductsRequest $request): GetProductsResponse
    {
        return new GetProductsResponse($request->getTitle().'OLOLOLOLO');
    }
}