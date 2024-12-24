<?php

namespace OV\JsonRPCAPIBundle\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\RPC\V1\GetProducts\GetProductsRequest;
use OV\JsonRPCAPIBundle\RPC\V1\GetProducts\GetProductsResponse;
use OV\JsonRPCAPIBundle\RPC\V1\GetProducts\Product;

#[JsonRPCAPI(methodName: 'getProducts', type: 'POST', ignoreInSwagger: true)]
class GetProductsMethod
{
    public function call(GetProductsRequest $request): GetProductsResponse
    {
        $response = new GetProductsResponse();

        $products = [
            [
                'id' => 1,
                'active' => true,
                'title' => 'Product 1',
            ],
            [
                'id' => 2,
                'active' => true,
                'title' => 'Product 2',
            ],
            [
                'id' => 3,
                'active' => false,
                'title' => 'Product 3',
            ],
        ];

        foreach ($products as $product) {
            $response->addProduct(
                (new Product())
                    ->setActive($product['active'])
                    ->setId($product['id'])
                    ->setTitle($product['title'])
            );
        }

        return $response;
    }
}