<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\RPC\V1\GetProductsMethod;
use OV\JsonRPCAPIBundle\RPC\V1\GetProducts\GetProductsRequest;
use Symfony\Component\HttpFoundation\JsonResponse;

final class FewObjectsResponseTest extends AbstractTest
{
    public function testController()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'getProducts',
            'params' => [
                'ids' => [1, 2, 3],
            ]
        ];

        $methodSpec = new MethodSpec(
            methodClass: GetProductsMethod::class,
            requestType: 'POST',
            summary: '',
            description: '',
            ignoreInSwagger: false,
            methodName: 'getProducts',
            allParameters: [['name' => 'ids', 'type' => 'array']],
            requiredParameters: [['name' => 'ids', 'type' => 'array']],
            request: GetProductsRequest::class,
            requestSetters: ['ids' => 'setIds'],
            validators: ['ids' => ['allowsNull' => false, 'type' => 'array']]
        );

        $responseData = [
            'jsonrpc' => '2.0',
            'result' => [
                'success' => true,
                'products' => [
                    [
                        'active' => true,
                        'id' => 1,
                        'title' => 'Product 1',
                    ],
                    [
                        'active' => true,
                        'id' => 2,
                        'title' => 'Product 2',
                    ],
                    [
                        'active' => false,
                        'id' => 3,
                        'title' => 'Product 3',
                    ],
                ],
            ],
            'id' => null,
        ];

        $result = $this->executeControllerTest($data, $methodSpec);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }
}