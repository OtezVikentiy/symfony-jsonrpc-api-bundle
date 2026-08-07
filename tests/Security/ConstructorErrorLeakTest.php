<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Security;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\RPC\V1\GetPicture\Request;
use OV\JsonRPCAPIBundle\RPC\V1\GetPictureMethod;
use OV\JsonRPCAPIBundle\Tests\Controller\AbstractControllerTestCase;

/**
 * A request DTO whose constructor rejects the supplied value raises a TypeError, and the text the
 * engine puts in it names the absolute path of the file that made the call, the line number and the
 * fully qualified class name. Handing that to the caller bypasses expose_internal_errors entirely:
 * the failure is wrapped in a JRPCException, and ErrorSanitizer passes those through by design.
 */
final class ConstructorErrorLeakTest extends AbstractControllerTestCase
{
    private const WRONGLY_TYPED_PAYLOAD = ['jsonrpc' => '2.0', 'method' => 'GetPicture', 'params' => ['id' => 'not-an-int'], 'id' => 1];

    public function testConstructorTypeErrorDoesNotLeakServerPaths(): void
    {
        $this->setValidateMethodExpectation('never');

        $body = (string) $this->executeControllerTest(self::WRONGLY_TYPED_PAYLOAD, $this->getPictureSpec())->getContent();

        $this->assertStringNotContainsString(__DIR__, $body, 'the filesystem path of the project must not reach the caller');
        $this->assertStringNotContainsString('RequestHandler.php', $body, 'the internal file that made the call must not be named');
        $this->assertStringNotContainsString('called in', $body);
        $this->assertStringNotContainsString(' on line ', $body);
    }

    public function testConstructorTypeErrorDoesNotLeakInternalClassNames(): void
    {
        $this->setValidateMethodExpectation('never');

        $body = (string) $this->executeControllerTest(self::WRONGLY_TYPED_PAYLOAD, $this->getPictureSpec())->getContent();

        $this->assertStringNotContainsString(Request::class, $body, 'the request DTO is an internal detail of the server');
        $this->assertStringNotContainsString('OV\\JsonRPCAPIBundle', $body);
        $this->assertStringNotContainsString('__construct', $body);
    }

    public function testConstructorTypeErrorStillTellsTheCallerWhichFieldIsWrong(): void
    {
        $this->setValidateMethodExpectation('never');

        $payload = json_decode(
            (string) $this->executeControllerTest(self::WRONGLY_TYPED_PAYLOAD, $this->getPictureSpec())->getContent(),
            true
        );

        $this->assertSame(JRPCException::INVALID_PARAMS, $payload['error']['code']);
        $this->assertStringContainsString('[id]', $payload['error']['message'], 'a sanitised message is still expected to name the offending field');
        $this->assertStringContainsString('int', $payload['error']['message']);
    }

    private function getPictureSpec(): MethodSpec
    {
        return new MethodSpec(
            methodClass: GetPictureMethod::class,
            requestType: 'POST',
            methodName: 'GetPicture',
            requestMetadata: new RequestMetadata(
                request: Request::class,
                allParameters: [['name' => 'id', 'type' => 'int']],
                requiredParameters: [['name' => 'id', 'type' => 'int']],
                requestGetters: ['id' => 'getId'],
                requestSetters: ['id' => 'setId'],
                requestAdders: [],
                validators: ['id' => ['allowsNull' => false, 'type' => 'int']],
            ),
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: true),
        );
    }
}
