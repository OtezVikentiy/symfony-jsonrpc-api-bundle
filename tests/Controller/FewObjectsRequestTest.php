<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\RPC\V1\CreateSome\Token;
use OV\JsonRPCAPIBundle\RPC\V1\CreateSomeMethod;
use OV\JsonRPCAPIBundle\RPC\V1\CreateSome\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

final class FewObjectsRequestTest extends AbstractTest
{
    public function testController()
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'CreateSomeMethod',
            'params' => [
                'tokens' => [
                    [
                        'name' => 'name1',
                        'value' => 'value1',
                        'summary' => 'summary1',
                    ],
                    [
                        'name' => 'name2',
                        'value' => 'value2',
                        'summary' => 'summary2',
                    ],
                    [
                        'name' => 'name3',
                        'value' => 'value3',
                        'summary' => 'summary3',
                    ],
                ],
            ]
        ];

        $methodSpec = new MethodSpec(
            methodClass: CreateSomeMethod::class,
            requestType: 'POST',
            summary: '',
            description: '',
            ignoreInSwagger: true,
            methodName: 'CreateSomeMethod',
            allParameters: [['name' => 'tokens', 'type' => Token::class]],
            requiredParameters: [],
            request: Request::class,
            requestGetters: ['tokens' => 'getTokens'],
            requestSetters: ['tokens' => 'setTokens'],
            requestAdders: ['token' => 'addToken'],
            validators: []
        );

        $responseData = [
            'jsonrpc' => '2.0',
            'result' => [
                'success' => true,
                'responseString' => 'name1_value1_summary1 ||| name2_value2_summary2 ||| name3_value3_summary3'
            ],
            'id' => null,
        ];

        $result = $this->executeControllerTest($data, $methodSpec);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }
}