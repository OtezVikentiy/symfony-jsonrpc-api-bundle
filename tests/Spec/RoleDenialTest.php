<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Spec;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\RPC\V1\Subtract\SubtractRequest;
use OV\JsonRPCAPIBundle\RPC\V1\SubtractMethod;
use OV\JsonRPCAPIBundle\RPC\V1\Test\TestRequest;
use OV\JsonRPCAPIBundle\RPC\V1\TestMethod;
use OV\JsonRPCAPIBundle\Tests\Controller\AbstractControllerTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * A role denial must surface as a JSON-RPC error object, not a bare HTTP 403 body.
 */
final class RoleDenialTest extends AbstractControllerTestCase
{
    protected bool $isGranted = false;

    private static function deniedSpec(): MethodSpec
    {
        return new MethodSpec(
            methodClass: TestMethod::class,
            requestType: 'POST',
            methodName: 'test',
            requestMetadata: new RequestMetadata(
                request: TestRequest::class,
                allParameters: [],
                requiredParameters: [],
                requestGetters: [],
                requestSetters: [],
                requestAdders: [],
                validators: [],
            ),
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: false),
            roles: ['ROLE_ADMIN'],
        );
    }

    private static function subtractSpec(): MethodSpec
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
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: false),
        );
    }

    public function testSingleRequestDeniedByRoleReturnsJsonRpcError(): void
    {
        $data = ['jsonrpc' => '2.0', 'method' => 'test', 'id' => '1'];

        $this->setValidateMethodExpectation('any');
        $result = $this->executeControllerTest(data: $data, methodSpecs: [self::deniedSpec()]);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());

        $decoded = json_decode((string) $result->getContent(), true);
        $this->assertEquals('2.0', $decoded['jsonrpc']);
        $this->assertEquals(JRPCException::SERVER_ERROR, $decoded['error']['code']);
        $this->assertEquals('1', $decoded['id']);
    }

    public function testBatchWithDeniedFirstElementStillProcessesSecond(): void
    {
        $data = [
            ['jsonrpc' => '2.0', 'method' => 'test', 'id' => '1'],
            ['jsonrpc' => '2.0', 'method' => 'subtract', 'params' => [42, 23], 'id' => '2'],
        ];

        $result = $this->executeControllerTest(data: $data, methodSpecs: [self::deniedSpec(), self::subtractSpec()]);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());

        $decoded = json_decode((string) $result->getContent(), true);
        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);

        $this->assertEquals(JRPCException::SERVER_ERROR, $decoded[0]['error']['code']);
        $this->assertEquals('1', $decoded[0]['id']);

        $this->assertEquals(['result' => 19], $decoded[1]['result']);
        $this->assertEquals('2', $decoded[1]['id']);
    }
}
