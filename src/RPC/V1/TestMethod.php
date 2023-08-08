<?php

namespace OV\JsonRPCAPIBundle\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\RPC\V1\Test\TestRequest;
use OV\JsonRPCAPIBundle\RPC\V1\Test\TestResponse;

/**
 * @JsonRPCAPI(methodName = "test")
 */
#[JsonRPCAPI(methodName: 'test')]
class TestMethod
{
    /**
     * @param TestRequest $request
     * @return TestResponse
     */
    public function call(TestRequest $request): TestResponse
    {
        //... do some api logic here and return Response
        //... use this class as any other service in Symfony

        return new TestResponse($request->getTitle());
    }
}