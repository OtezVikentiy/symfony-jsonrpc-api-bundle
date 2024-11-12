<?php

namespace App\RPC\V1;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Product;
use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use App\RPC\V1\GetProducts\Product as ApiProduct;
use App\RPC\V1\GetProducts\GetProductsRequest;
use App\RPC\V1\GetProducts\GetProductsResponse;

#[JsonRPCAPI(methodName: 'getProducts', type: 'POST')]
class GetProductsMethod
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * @param GetProductsRequest $request // !!!ATTENTION!!! Do not rename this param - just change type, but not the name of variable
     * @return GetProductsResponse
     */
    public function call(GetProductsRequest $request): GetProductsResponse
    {
        $products = $this->em->getRepository(Product::class)->findBy(['id' => $request->getIds()]);

        $response = new Response();

        foreach ($products as $product) {
            $response->addProduct(
                (new ApiProduct())
                    ->setActive($product->isActive())
                    ->setId($product->getId())
                    ->setTitle($product->getTitle())
            );
        }

        return $response;
    }
}