<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\RPC\V1\GetFilteredDataMethod;
use OV\JsonRPCAPIBundle\RPC\V1\GetFilteredDataMethod\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

final class GetFilteredDataTest extends AbstractTest
{
    public function testRpcCallBatch()
    {
        $data = [
            [
                'jsonrpc' => '2.0',
                'method' => 'GetFilteredDataMethod',
                'params' => [
                    'filter' => [
                        'id' => 1,
                        'title' => 'azaza',
                        'finished' => true,
                    ],
                    'limit' => 2,
                    'offset' => 0,
                ],
                'id' => '5',
            ],
        ];

        $methodSpecs = [
            new MethodSpec(
                methodClass: GetFilteredDataMethod::class,
                requestType: 'POST',
                summary: '',
                description: '',
                ignoreInSwagger: true,
                methodName: 'GetFilteredDataMethod',
                allParameters: [],
                requiredParameters: [],
                request: Request::class,
                requestSetters: [],
                requestAdders: [],
                validators: []
            ),
        ];
        $responseData = [
            [
                'jsonrpc' => '2.0',
                'result' => ['hello', 5],
                'id' => '9',
            ],
        ];

        $result = $this->executeControllerTest(data: $data, methodSpecs: $methodSpecs);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }
}