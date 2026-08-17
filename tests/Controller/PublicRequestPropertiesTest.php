<?php

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use Symfony\Component\HttpFoundation\JsonResponse;

final class PublicRequestPropertiesTest extends AbstractControllerTestCase
{
    protected bool $useRealValidator = true;

    public function testPromotedAndNonConstructorPublicPropertiesHydrateWithoutAccessors(): void
    {
        $result = $this->executeControllerTest([
            'jsonrpc' => '2.0',
            'method' => 'publicProperties',
            'params' => [
                'id' => 42,
                'label' => 'ready',
                'note' => 'directly hydrated',
            ],
            'id' => '1',
        ], $this->methodSpec());

        self::assertInstanceOf(JsonResponse::class, $result);
        self::assertSame([
            'jsonrpc' => '2.0',
            'result' => [
                'id' => 42,
                'label' => 'ready',
                'note' => 'directly hydrated',
            ],
            'id' => '1',
        ], json_decode((string) $result->getContent(), true));
    }

    public function testWrongTypedPublicPropertyStillReturnsInvalidParams(): void
    {
        $result = $this->executeControllerTest([
            'jsonrpc' => '2.0',
            'method' => 'publicProperties',
            'params' => [
                'id' => 42,
                'label' => 123,
            ],
            'id' => '1',
        ], $this->methodSpec());

        self::assertInstanceOf(JsonResponse::class, $result);
        $payload = json_decode((string) $result->getContent(), true);
        self::assertSame(-32602, $payload['error']['code']);
        self::assertStringContainsString('[label] - This value should be of type string', $payload['error']['message']);
    }

    public function testMissingRequiredPublicPropertyStillReturnsInvalidParams(): void
    {
        $result = $this->executeControllerTest([
            'jsonrpc' => '2.0',
            'method' => 'publicProperties',
            'params' => ['id' => 42],
            'id' => '1',
        ], $this->methodSpec());

        self::assertInstanceOf(JsonResponse::class, $result);
        $payload = json_decode((string) $result->getContent(), true);
        self::assertSame(-32602, $payload['error']['code']);
        self::assertStringContainsString('[label] - This field is missing', $payload['error']['message']);
    }

    private function methodSpec(): MethodSpec
    {
        return new MethodSpec(
            methodClass: PublicPropertiesMethod::class,
            requestType: 'POST',
            methodName: 'publicProperties',
            requestMetadata: new RequestMetadata(
                request: PublicPropertiesRequest::class,
                allParameters: [
                    ['name' => 'id', 'type' => 'int'],
                    ['name' => 'label', 'type' => 'string'],
                    ['name' => 'note', 'type' => 'string', 'defaultValue' => null],
                ],
                requiredParameters: [
                    ['name' => 'id', 'type' => 'int'],
                ],
                requestGetters: [],
                requestSetters: [],
                requestAdders: [],
                validators: [
                    'id' => ['allowsNull' => false, 'type' => 'int'],
                    'label' => ['allowsNull' => false, 'type' => 'string'],
                    'note' => ['allowsNull' => true, 'type' => 'string'],
                ],
            ),
            swaggerMetadata: new SwaggerMetadata('', '', false),
        );
    }
}

final class PublicPropertiesRequest
{
    public string $label;
    public ?string $note = null;

    public function __construct(public readonly int $id)
    {
    }
}

#[JsonRPCAPI(methodName: 'publicProperties', type: 'POST', version: 1, ignoreInSwagger: true)]
final class PublicPropertiesMethod
{
    public function call(PublicPropertiesRequest $request): array
    {
        return [
            'id' => $request->id,
            'label' => $request->label,
            'note' => $request->note,
        ];
    }
}
