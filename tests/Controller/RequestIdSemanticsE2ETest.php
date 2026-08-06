<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\RPC\V1\Subtract\SubtractRequest;
use OV\JsonRPCAPIBundle\RPC\V1\SubtractMethod;
use Symfony\Component\HttpFoundation\JsonResponse;

final class RequestIdSemanticsE2ETest extends AbstractControllerTestCase
{
    private function subtractMethodSpec(): MethodSpec
    {
        return new MethodSpec(
            methodClass: SubtractMethod::class,
            requestType: 'POST',
            methodName: 'subtract',
            requestMetadata: new RequestMetadata(
                request: SubtractRequest::class,
                allParameters: [['name' => 'params', 'type' => 'array']],
                requiredParameters: [],
                requestGetters: ['params' => 'getParams'],
                requestSetters: ['params' => 'setParams'],
                requestAdders: [],
                validators: ['params' => ['allowsNull' => false, 'type' => 'array']],
            ),
            swaggerMetadata: new SwaggerMetadata(
                summary: '',
                description: '',
                ignoreInSwagger: false,
            ),
        );
    }

    public function testExplicitNullIdReceivesNonEmptyResponse(): void
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'subtract',
            'params' => [42, 23],
            'id' => null,
        ];

        $responseData = [
            'jsonrpc' => '2.0',
            'result' => ['result' => 19],
            'id' => null,
        ];

        $result = $this->executeControllerTest($data, $this->subtractMethodSpec());

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertNotSame('', $result->getContent());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }

    public function testMissingIdReceivesNoResponseByDefault(): void
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'subtract',
            'params' => [42, 23],
            // no 'id' key at all — this is a notification, strictNotifications defaults to true
        ];

        $result = $this->executeControllerTest($data, $this->subtractMethodSpec());

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        // Spec 4.1: "The Server MUST NOT reply to a Notification." A single (non-batch)
        // notification and a batch made up entirely of notifications must therefore
        // produce the same empty body (see BatchRequestWithEmptyResponseTest).
        $this->assertSame('', $result->getContent());
    }

    public function testExplicitNullIdErrorResponseIncludesNullId(): void
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'unknown_method',
            'id' => null,
        ];

        $responseData = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32601,
                'message' => 'Method not found.',
            ],
            'id' => null,
        ];

        $this->setValidateMethodExpectation('never');
        $result = $this->executeControllerTest($data, $this->subtractMethodSpec());

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());
        $this->assertEquals(json_encode($responseData), $result->getContent());
    }
}
