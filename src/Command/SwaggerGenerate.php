<?php

namespace OV\JsonRPCAPIBundle\Command;

use OV\JsonRPCAPIBundle\Core\Annotation\SwaggerArrayProperty;
use OV\JsonRPCAPIBundle\Core\Annotation\SwaggerProperty;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use OV\JsonRPCAPIBundle\Swagger\Informational\Contact;
use OV\JsonRPCAPIBundle\Swagger\Informational\Info;
use OV\JsonRPCAPIBundle\Swagger\Informational\License;
use OV\JsonRPCAPIBundle\Swagger\Informational\Openapi;
use OV\JsonRPCAPIBundle\Swagger\Path;
use OV\JsonRPCAPIBundle\Swagger\RequestBody;
use OV\JsonRPCAPIBundle\Swagger\Response;
use OV\JsonRPCAPIBundle\Swagger\Schema;
use OV\JsonRPCAPIBundle\Swagger\SchemaItem;
use OV\JsonRPCAPIBundle\Swagger\SchemaProperty;
use OV\JsonRPCAPIBundle\Swagger\Server;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'ov:swagger:generate')]
final class SwaggerGenerate extends Command
{
    private array $components = [];

    public function __construct(
        private readonly string $ovJsonRpcApiSwaggerPath,
        private readonly array $swagger,
        private readonly MethodSpecCollection $methodSpecCollection,
        private readonly bool $passToOutput = false,
        string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @throws ReflectionException
     * @noinspection PhpUnused
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach ($this->swagger as $name => $item) {
            $yaml = $this->generateSwaggerYaml($item);

            if ($this->passToOutput) {
                $output->writeln($yaml);
            } else {
                file_put_contents(sprintf('%s%s.yaml', $this->ovJsonRpcApiSwaggerPath, $name), $yaml);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @throws ReflectionException
     */
    private function generateSwaggerYaml(array $item): string
    {
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

        [$tags, $paths] = $this->generateApis($item['auth_token_name'], $item['auth_token_test_value'], $item['api_version']);

        $swagger = new Openapi($info, $servers, $tags, $paths, $this->components);

        return Yaml::dump($swagger->toArray(), 12);
    }

    /**
     * @throws ReflectionException
     */
    private function generateApis(string $authTokenName, string $authTokenDefaultValue, int $apiVersion): array
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
                $globalRequest->addProperty(new SchemaProperty(name: 'jsonrpc', type: 'string', default: '2.0', example: '2.0'));
                $globalRequest->addRequired(new SchemaProperty(name: 'jsonrpc', type: 'string', default: '2.0', example: '2.0'));

                $globalRequest->addProperty(
                    new SchemaProperty(
                        name: 'method',
                        type: 'string',
                        default: $method->getMethodName(),
                        example: $method->getMethodName()
                    )
                );
                $globalRequest->addRequired(
                    new SchemaProperty(
                        name: 'method',
                        type: 'string',
                        default: $method->getMethodName(),
                        example: $method->getMethodName()
                    )
                );

                $requestSchema = new Schema(sprintf('%sRequest', $name));
                $addIdToGlobalRequest = false;

                foreach ($method->getRequiredParameters() as $requiredParameter) {
                    $type = $requiredParameter['type'];
                    if ($type === 'int') {
                        $type = 'integer';
                    }  elseif ($type === 'bool') {
                        $type = 'boolean';
                    }
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
                    $type = $parameter['type'];
                    if ($type === 'int') {
                        $type = 'integer';
                    } elseif ($type === 'bool') {
                        $type = 'boolean';
                    }
                    $prop = new SchemaProperty($parameter['name'], $type);
                    $requestSchema->addProperty($prop);
                }

                unset($parameters);

                $this->components[] = $requestSchema;

                $globalRequest->addProperty(new SchemaProperty(name: 'params', ref: sprintf('%sRequest', $name)));
                $globalRequest->addRequired(new SchemaProperty(name: 'params', ref: sprintf('%sRequest', $name)));
                if ($addIdToGlobalRequest) {
                    $globalRequest->addProperty(new SchemaProperty(name: 'id', type: 'integer', default: '0', example: '0'));
                    $globalRequest->addRequired(new SchemaProperty(name: 'id', type: 'integer', default: '0', example: '0'));
                }

                $this->components[] = $globalRequest;

                $requestBody = new RequestBody(sprintf('%sMainRequest', $name));

                $methodRef = new ReflectionClass($method->getMethodClass());
                $callMethod = $methodRef->getMethod('call');
                $returnType = $callMethod->getReturnType();

                if ($returnType instanceof UnionType) {
                    continue; //Todo костыль который надо исправить
                }

                $responseClassRef = new ReflectionClass($callMethod->getReturnType()?->getName());
                $responseProperties = $responseClassRef->getProperties();
                $requiredPropertiesOfResponse = $this->getRequiredPropertiesList($responseClassRef);

                $responseSchemaName = str_replace('\\', '_', $responseClassRef->getName());
                $responseSchema = new Schema(sprintf('%sResponse', $responseSchemaName));
                $responseSchema->addProperty(new SchemaProperty(name: 'jsonrpc', type: 'string', default: '2.0', example: '2.0'));
                $responseSchema->addRequired(new SchemaProperty(name: 'jsonrpc', type: 'string', default: '2.0', example: '2.0'));

                foreach ($responseProperties as $responseProperty) {
                    $this->processProperty($responseProperty, $responseSchema, in_array($responseProperty->getName(), $requiredPropertiesOfResponse));
                }

                $this->components[] = $responseSchema;

                if ($addIdToGlobalRequest) {
                    $responseSchema->addProperty(new SchemaProperty(name: 'id', type: 'integer', default: '0', example: '0'));
                    $responseSchema->addRequired(new SchemaProperty(name: 'id', type: 'integer', default: '0', example: '0'));
                }

                $response = new Response('200', sprintf('%sResponse', $responseSchemaName));

                $path = new Path(
                    name: '/' . $this->camelToSnake($method->getMethodName(), '_'),
                    methodType: $method->getRequestType(),
                    summary: $method->getSummary(),
                    description: $method->getDescription(),
                    requestBody: $requestBody,
                    tags: $method->getTags(),
                    responses: [$response],
                    parameters: [
                        [
                            'in' => 'header',
                            'name' => $authTokenName,
                            'schema' => ['type' => 'string'],
                            'example' => $authTokenDefaultValue
                        ]
                    ],
                );
                $paths[] = $path;
            }
        }

        return [array_values($tags), $paths];
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

        $type = $property->getType()->getName();
        if ($type === 'int') {
            $type = 'integer';
        } elseif ($type === 'bool') {
            $type = 'boolean';
        }

        if (!in_array($type, ['array', 'boolean', 'integer', 'number', 'object', 'string'])) {
            $this->processObjectProperty($property->getType()->getName(), $schemaProperty, $schema, $required);
            return;
        }

        if ($type !== 'array') {
            $schemaProperty->setType($type);
            $schema->addProperty($schemaProperty);
            if ($required) $schema->addRequired($schemaProperty);
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
                    $schema->addProperty($schemaProperty);
                    if ($required) $schema->addRequired($schemaProperty);
                    return;
                } else {
                    $this->processObjectProperty($attributeType, $schemaProperty, $schema, $required, true);
                    return;
                }
            }
        }

        $schemaProperty->setType($type);
        $schema->addProperty($schemaProperty);
        if ($required) $schema->addRequired($schemaProperty);
    }

    private function processObjectProperty(
        string $name,
        SchemaProperty $schemaProperty,
        Schema $schema,
        bool $required,
        bool $schemaItem = false
    ): void {
        $innerSchema = new Schema(str_replace('\\', '_', $name));
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
                ->setItems(new SchemaItem(type: 'object', ref: str_replace('\\', '_', $name)));
            $schema->addProperty($schemaProperty);
            if ($required) $schema->addRequired($schemaProperty);
        } else {
            $schemaProperty
                ->setType('object')
                ->setRef(str_replace('\\', '_', $name));
            $schema->addProperty($schemaProperty);
            if ($required) $schema->addRequired($schemaProperty);
        }
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

    private function camelToSnake(string $string, string $us = "-"): string
    {
        return strtolower(preg_replace('/(?<=\d)(?=[A-Za-z])|(?<=[A-Za-z])(?=\d)|(?<=[a-z])(?=[A-Z])/', $us, $string));
    }
}