<?php

namespace OV\JsonRPCAPIBundle\Swagger;

use OV\JsonRPCAPIBundle\Core\Annotation\SwaggerArrayProperty;
use OV\JsonRPCAPIBundle\Core\Annotation\SwaggerProperty;
use OV\JsonRPCAPIBundle\Core\Response\PlainResponseInterface;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use OV\JsonRPCAPIBundle\Swagger\Informational\Contact;
use OV\JsonRPCAPIBundle\Swagger\Informational\Info;
use OV\JsonRPCAPIBundle\Swagger\Informational\License;
use OV\JsonRPCAPIBundle\Swagger\Informational\Openapi;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use ReflectionUnionType;
use Symfony\Component\Yaml\Yaml;

final class SwaggerSchemaBuilder
{
    private array $components = [];
    private array $processedClasses = [];

    public function __construct(
        private readonly MethodSpecCollection $methodSpecCollection,
    ) {
    }

    /**
     * @throws ReflectionException
     */
    public function build(array $item): string
    {
        $this->components = [];
        $this->processedClasses = [];

        $info = new Info(
            $item['info']['title'],
            $item['info']['description'],
            $item['info']['terms_of_service_url'],
            $item['api_version'],
            new Contact($item['info']['contact']['name'], $item['info']['contact']['url'], $item['info']['contact']['email']),
            new License($item['info']['license'], $item['info']['licenseUrl'])
        );

        $basePathVariables = $item['base_path_variables'];
        $testPathVariables = $item['test_path_variables'];

        $basePath = sprintf('%s/api/v%s', $item['base_path'], $item['api_version']);
        foreach ($basePathVariables as $basePathVariable) {
            $basePath = str_replace(sprintf('{%s}', $basePathVariable['name']), $basePathVariable['value'], $basePath);
        }

        $testPath = sprintf('%s/api/v%s', $item['test_path'], $item['api_version']);
        foreach ($testPathVariables as $testPathVariable) {
            $testPath = str_replace(sprintf('{%s}', $testPathVariable['name']), $testPathVariable['value'], $testPath);
        }

        $servers = [
            new Server($basePath, $item['base_path_description'] ?? ''),
            new Server($testPath, $item['test_path_description'] ?? ''),
        ];

        $authTokenName = $item['auth_token_name'];

        [$tags, $paths] = $this->generateApis($item['api_version']);

        $this->addJsonRpcErrorResponseSchema();

        $swagger = new Openapi(
            info: $info,
            servers: $servers,
            tags: $tags,
            paths: $paths,
            components: $this->components,
            securitySchemeName: 'ApiKeyAuth',
            securityScheme: [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => $authTokenName,
            ],
        );

        return Yaml::dump($swagger->toArray(), 12);
    }

    /**
     * @throws ReflectionException
     */
    private function generateApis(int $apiVersion): array
    {
        $tags = [];
        $paths = [];

        $methods = $this->methodSpecCollection->getAllMethods();
        foreach ($methods as $version => $methodsArray) {
            if ($version !== $apiVersion) {
                continue;
            }

            foreach ($methodsArray as $name => $method) {
                if ($method->isIgnoreInSwagger()) {
                    continue;
                }

                foreach ($method->getTags() as $tag) {
                    $tags[$tag] = ['name' => $tag];
                }

                $parameters = [];

                $globalRequest = new Schema(sprintf('%sMainRequest', $name));
                $jsonrpc = $this->jsonrpcProperty();
                $globalRequest->addProperty($jsonrpc);
                $globalRequest->addRequired($jsonrpc);

                $methodProp = new SchemaProperty(
                    name: 'method',
                    type: 'string',
                    default: $method->getMethodName(),
                    example: $method->getMethodName()
                );
                $globalRequest->addProperty($methodProp);
                $globalRequest->addRequired($methodProp);

                $requestSchema = new Schema(sprintf('%sRequest', $name));
                $addIdToGlobalRequest = false;

                foreach ($method->getRequiredParameters() as $requiredParameter) {
                    $type = $this->normalizeType($requiredParameter['type']);
                    $prop = new SchemaProperty($requiredParameter['name'], $type);
                    $requestSchema->addProperty($prop);
                    $requestSchema->addRequired($prop);
                    $parameters[$requiredParameter['name']] = $requiredParameter['name'];
                }

                foreach ($method->getAllParameters() as $parameter) {
                    if ($parameter['name'] === 'id') {
                        $addIdToGlobalRequest = true;
                    }
                    if (isset($parameters[$parameter['name']])) {
                        continue;
                    }
                    $type = $this->normalizeType($parameter['type']);
                    $prop = new SchemaProperty($parameter['name'], $type);
                    $requestSchema->addProperty($prop);
                }

                unset($parameters);

                $this->components[] = $requestSchema;

                $paramsProp = new SchemaProperty(name: 'params', ref: sprintf('%sRequest', $name));
                $globalRequest->addProperty($paramsProp);
                $globalRequest->addRequired($paramsProp);
                if ($addIdToGlobalRequest) {
                    $idProp = $this->idProperty();
                    $globalRequest->addProperty($idProp);
                    $globalRequest->addRequired($idProp);
                }

                $this->components[] = $globalRequest;

                $requestBody = new RequestBody(sprintf('%sMainRequest', $name));

                $methodRef = new ReflectionClass($method->getMethodClass());
                $callMethod = $methodRef->getMethod('call');
                $returnType = $callMethod->getReturnType();

                $responseClassName = $this->resolveResponseClassName($returnType);
                if ($responseClassName === null) {
                    continue;
                }

                $responseClassRef = new ReflectionClass($responseClassName);
                $responseProperties = $responseClassRef->getProperties();
                $requiredPropertiesOfResponse = $this->getRequiredPropertiesList($responseClassRef);

                $responseSchemaName = $responseClassRef->getShortName();
                $responseSchema = new Schema(sprintf('%sResponse', $responseSchemaName));
                $jsonrpcResp = $this->jsonrpcProperty();
                $responseSchema->addProperty($jsonrpcResp);
                $responseSchema->addRequired($jsonrpcResp);

                foreach ($responseProperties as $responseProperty) {
                    $this->processProperty($responseProperty, $responseSchema, in_array($responseProperty->getName(), $requiredPropertiesOfResponse));
                }

                if ($addIdToGlobalRequest) {
                    $idPropResp = $this->idProperty();
                    $responseSchema->addProperty($idPropResp);
                    $responseSchema->addRequired($idPropResp);
                }

                $this->components[] = $responseSchema;

                $response = new Response('200', sprintf('%sResponse', $responseSchemaName));

                $pathName = '/' . $this->camelToSnake($method->getMethodName(), '_');
                if ($method->getGroup() !== null) {
                    $pathName = '/' . $this->camelToSnake($method->getGroup(), '_') . $pathName;
                }

                $path = new Path(
                    name: $pathName,
                    methodType: $method->getRequestType(),
                    summary: $method->getSummary(),
                    description: $method->getDescription(),
                    requestBody: $requestBody,
                    tags: $method->getTags(),
                    responses: [$response],
                );
                $paths[] = $path;
            }
        }

        return [array_values($tags), $paths];
    }

    private function resolveResponseClassName(\ReflectionType $returnType): ?string
    {
        if ($returnType instanceof ReflectionUnionType) {
            foreach ($returnType->getTypes() as $type) {
                $typeName = $type->getName();
                if (!class_exists($typeName)) {
                    continue;
                }
                $typeReflection = new ReflectionClass($typeName);
                if (!$typeReflection->implementsInterface(PlainResponseInterface::class)) {
                    return $typeName;
                }
            }
            return null;
        }

        $typeName = $returnType->getName();
        if (class_exists($typeName)) {
            $typeReflection = new ReflectionClass($typeName);
            if ($typeReflection->implementsInterface(PlainResponseInterface::class)) {
                return null;
            }
        }

        return $typeName;
    }

    private function processProperty(ReflectionProperty $property, Schema $schema, bool $required): void
    {
        $swaggerPropertyAttribute = $property->getAttributes(name: SwaggerProperty::class);

        $default = null;
        $example = null;
        $format = null;

        if (!empty($swaggerPropertyAttribute[0])) {
            $default = $swaggerPropertyAttribute[0]->getArguments()['default'] ?? null;
            $example = $swaggerPropertyAttribute[0]->getArguments()['example'] ?? null;
            $format = $swaggerPropertyAttribute[0]->getArguments()['format'] ?? null;
        }

        $schemaProperty = new SchemaProperty(name: $property->getName());
        if (!is_null($default)) {
            $schemaProperty->setDefault($default);
        }
        if (!is_null($example)) {
            $schemaProperty->setExample($example);
        }
        if (!is_null($format)) {
            $schemaProperty->setFormat($format);
        }

        $propertyType = $property->getType();
        if ($propertyType === null) {
            $schemaProperty->setType('string');
            $schema->addPropertyWithRequired($schemaProperty, $required);
            return;
        }

        $type = $propertyType->getName();
        $type = $this->normalizeType($type);

        if (!in_array($type, ['array', 'boolean', 'integer', 'number', 'object', 'string'])) {
            $this->processObjectProperty($property->getType()->getName(), $schemaProperty, $schema, $required);
            return;
        }

        if ($type !== 'array') {
            $schemaProperty->setType($type);
            $schema->addPropertyWithRequired($schemaProperty, $required);
            return;
        }

        $responseArrayPropertyAttributes = $property->getAttributes(SwaggerArrayProperty::class);
        foreach ($responseArrayPropertyAttributes as $responseArrayPropertyAttribute) {
            if ($responseArrayPropertyAttribute->getName() === SwaggerArrayProperty::class) {
                $attributeType = $responseArrayPropertyAttribute->getArguments()['type'];
                $ofClass = $responseArrayPropertyAttribute->getArguments()['ofClass'] ?? false;
                if (!$ofClass) {
                    $schemaProperty
                        ->setType($type)
                        ->setItems(new SchemaItem(type: $attributeType));
                    $schema->addPropertyWithRequired($schemaProperty, $required);
                    return;
                } else {
                    $this->processObjectProperty($attributeType, $schemaProperty, $schema, $required, true);
                    return;
                }
            }
        }

        $schemaProperty->setType($type);
        $schema->addPropertyWithRequired($schemaProperty, $required);
    }

    private function processObjectProperty(
        string $name,
        SchemaProperty $schemaProperty,
        Schema $schema,
        bool $required,
        bool $schemaItem = false
    ): void {
        $shortName = (new ReflectionClass($name))->getShortName();

        if (isset($this->processedClasses[$name])) {
            if ($schemaItem) {
                $schemaProperty
                    ->setType('array')
                    ->setItems(new SchemaItem(type: 'object', ref: $shortName));
            } else {
                $schemaProperty->setRef($shortName);
            }
            $schema->addPropertyWithRequired($schemaProperty, $required);
            return;
        }
        $this->processedClasses[$name] = true;

        $innerSchema = new Schema($shortName);
        $reflection = new ReflectionClass($name);
        $requiredPropertiesOfResponse = $this->getRequiredPropertiesList($reflection);
        foreach ($reflection->getProperties() as $reflectionProperty) {
            $this->processProperty(
                $reflectionProperty,
                $innerSchema,
                in_array($reflectionProperty->getName(), $requiredPropertiesOfResponse)
            );
        }
        $this->components[] = $innerSchema;
        if ($schemaItem) {
            $schemaProperty
                ->setType('array')
                ->setItems(new SchemaItem(type: 'object', ref: $shortName));
        } else {
            $schemaProperty->setRef($shortName);
        }
        $schema->addPropertyWithRequired($schemaProperty, $required);
    }

    private function addJsonRpcErrorResponseSchema(): void
    {
        $errorDetailSchema = new Schema('JsonRpcError');
        $errorDetailSchema->addProperty(new SchemaProperty(name: 'code', type: 'integer'));
        $errorDetailSchema->addRequired(new SchemaProperty(name: 'code', type: 'integer'));
        $errorDetailSchema->addProperty(new SchemaProperty(name: 'message', type: 'string'));
        $errorDetailSchema->addRequired(new SchemaProperty(name: 'message', type: 'string'));
        $this->components[] = $errorDetailSchema;

        $errorResponseSchema = new Schema('JsonRpcErrorResponse');
        $jsonrpc = $this->jsonrpcProperty();
        $errorResponseSchema->addProperty($jsonrpc);
        $errorResponseSchema->addRequired($jsonrpc);
        $errorProp = new SchemaProperty(name: 'error', ref: 'JsonRpcError');
        $errorResponseSchema->addProperty($errorProp);
        $errorResponseSchema->addRequired($errorProp);
        $errorResponseSchema->addProperty(new SchemaProperty(name: 'id', type: 'integer'));
        $this->components[] = $errorResponseSchema;
    }

    private function getRequiredPropertiesList(ReflectionClass $reflection): array
    {
        $constructor = $reflection->getConstructor();
        $requiredPropertiesOfResponse = [];
        if ($constructor) {
            $parameters = $constructor->getParameters();
            foreach ($parameters as $parameter) {
                if ($parameter->isDefaultValueAvailable()) {
                    continue;
                }
                $requiredPropertiesOfResponse[] = $parameter->getName();
            }
        }
        return $requiredPropertiesOfResponse;
    }

    private function normalizeType(string $type): string
    {
        return match ($type) {
            'int' => 'integer',
            'bool' => 'boolean',
            'float', 'double' => 'number',
            default => $type,
        };
    }

    private function camelToSnake(string $string, string $us = "-"): string
    {
        return strtolower(preg_replace('/(?<=\d)(?=[A-Za-z])|(?<=[A-Za-z])(?=\d)|(?<=[a-z])(?=[A-Z])/', $us, $string));
    }

    private function jsonrpcProperty(): SchemaProperty
    {
        return new SchemaProperty(name: 'jsonrpc', type: 'string', default: '2.0', example: '2.0');
    }

    private function idProperty(): SchemaProperty
    {
        return new SchemaProperty(name: 'id', type: 'integer', default: '0', example: '0');
    }
}
