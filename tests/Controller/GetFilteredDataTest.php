<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\RPC\V1\GetFilteredData;
use OV\JsonRPCAPIBundle\RPC\V1\GetFilteredData\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

final class GetFilteredDataTest extends AbstractTest
{
    public function testRpcCallBatch()
    {
        $data = [
            [
                'jsonrpc' => '2.0',
                'method' => 'GetFilteredData',
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
                methodClass: GetFilteredData::class,
                requestType: 'POST',
                summary: '',
                description: '',
                ignoreInSwagger: true,
                methodName: 'GetFilteredData',
                allParameters: [['name' => 'filter', 'type' => GetFilteredData\Filter::class],['name' => 'limit', 'type' => 'integer'],['name' => 'offset', 'type' => 'integer']],
                requiredParameters: [],
                request: Request::class,
                requestGetters: ['filter' => 'getFilter' ,'limit' => 'getLimit', 'offset' => 'getOffset'],
                requestSetters: ['filter' => 'setFilter' ,'limit' => 'setLimit', 'offset' => 'setOffset'],
                requestAdders: [],
                validators: ['filter' => ['allowsNull' => false, 'type' => GetFilteredData\Filter::class], 'limit' => ['allowsNull' => false, 'type' => 'integer'], 'offset' => ['allowsNull' => false, 'type' => 'integer']]
            ),
        ];
        $responseData = [
            [
                'jsonrpc' => '2.0',
                'result' => [
                    1,
                    'azaza',
                    true,
                ],
                'id' => '5',
            ],
        ];

        $result = $this->executeControllerTest(data: $data, methodSpecs: $methodSpecs);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }
}