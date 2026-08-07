<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Swagger;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use OV\JsonRPCAPIBundle\RPC\V1\CollisionAlpha\CollisionAlphaMethod;
use OV\JsonRPCAPIBundle\RPC\V1\CollisionBeta\CollisionBetaMethod;
use OV\JsonRPCAPIBundle\Swagger\SwaggerSchemaBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class SchemaNameCollisionTest extends TestCase
{
    public function testResponseClassesSharingAShortNameProduceDistinctSchemas(): void
    {
        $document = Yaml::parse($this->buildDocument());
        $schemas = $document['components']['schemas'];

        $this->assertArrayHasKey('collision_alphaResponse', $schemas);
        $this->assertArrayHasKey('collision_betaResponse', $schemas);

        $this->assertSame(
            '#/components/schemas/collision_alphaResponse',
            $document['paths']['/collision_alpha']['post']['responses'][200]['content']['application/json']['schema']['$ref']
        );
        $this->assertSame(
            '#/components/schemas/collision_betaResponse',
            $document['paths']['/collision_beta']['post']['responses'][200]['content']['application/json']['schema']['$ref']
        );
    }

    public function testNestedDtoClassesSharingAShortNameProduceDistinctSchemas(): void
    {
        $document = Yaml::parse($this->buildDocument());
        $schemas = $document['components']['schemas'];

        $alphaFilterSchema = 'OV.JsonRPCAPIBundle.RPC.V1.CollisionAlpha.Filter';
        $betaFilterSchema = 'OV.JsonRPCAPIBundle.RPC.V1.CollisionBeta.Filter';

        $this->assertArrayHasKey($alphaFilterSchema, $schemas);
        $this->assertArrayHasKey($betaFilterSchema, $schemas);
        $this->assertArrayHasKey('alphaField', $schemas[$alphaFilterSchema]['properties']);
        $this->assertArrayHasKey('betaField', $schemas[$betaFilterSchema]['properties']);

        $this->assertSame(
            '#/components/schemas/' . $alphaFilterSchema,
            $schemas['collision_alphaResponse']['properties']['filter']['$ref']
        );
        $this->assertSame(
            '#/components/schemas/' . $betaFilterSchema,
            $schemas['collision_betaResponse']['properties']['filter']['$ref']
        );
    }

    private function buildDocument(): string
    {
        $builder = new SwaggerSchemaBuilder($this->buildMethodSpecCollection());

        return $builder->build([
            'info' => [
                'title' => 'title',
                'description' => 'description',
                'terms_of_service_url' => 'terms_of_service_url',
                'contact' => [
                    'name' => 'name',
                    'url' => 'url',
                    'email' => 'email',
                ],
                'license' => 'license',
                'licenseUrl' => 'licenseUrl',
            ],
            'api_version' => '1',
            'base_path' => 'http://localhost',
            'base_path_description' => '',
            'base_path_variables' => [],
            'test_path' => 'http://localhost',
            'test_path_description' => '',
            'test_path_variables' => [],
            'auth_token_name' => 'X-AUTH-TOKEN',
        ]);
    }

    private function buildMethodSpecCollection(): MethodSpecCollection
    {
        $collection = new MethodSpecCollection();
        $collection->addMethodSpec(1, 'collision_alpha', $this->buildMethodSpec(CollisionAlphaMethod::class, 'collision_alpha'));
        $collection->addMethodSpec(1, 'collision_beta', $this->buildMethodSpec(CollisionBetaMethod::class, 'collision_beta'));

        return $collection;
    }

    private function buildMethodSpec(string $methodClass, string $methodName): MethodSpec
    {
        return new MethodSpec(
            methodClass: $methodClass,
            requestType: 'POST',
            methodName: $methodName,
            requestMetadata: new RequestMetadata(
                request: null,
                allParameters: [],
                requiredParameters: [],
                requestGetters: [],
                requestSetters: [],
                requestAdders: [],
                validators: [],
            ),
            swaggerMetadata: new SwaggerMetadata(
                summary: '',
                description: '',
                ignoreInSwagger: false,
                tags: [],
            ),
        );
    }
}
