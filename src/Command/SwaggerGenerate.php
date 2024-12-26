<?php

namespace OV\JsonRPCAPIBundle\Command;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use OV\JsonRPCAPIBundle\Swagger\Contact;
use OV\JsonRPCAPIBundle\Swagger\Info;
use OV\JsonRPCAPIBundle\Swagger\License;
use OV\JsonRPCAPIBundle\Swagger\Openapi;
use OV\JsonRPCAPIBundle\Swagger\Path;
use OV\JsonRPCAPIBundle\Swagger\RequestBody;
use OV\JsonRPCAPIBundle\Swagger\Response;
use OV\JsonRPCAPIBundle\Swagger\Schema;
use OV\JsonRPCAPIBundle\Swagger\SchemaProperty;
use OV\JsonRPCAPIBundle\Swagger\Server;
use OV\JsonRPCAPIBundle\Swagger\Tag;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'ov:swagger:generate')]
class SwaggerGenerate extends Command
{
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

        $servers = [
            new Server(sprintf('%s/api/v%s', $item['base_path'], $item['api_version']))
        ];

        [$tags, $paths, $components] = $this->generateApis($item['auth_token_name'], $item['auth_token_test_value']);

        $swagger = new Openapi($info, $servers, $tags, $paths, $components);

        return Yaml::dump($swagger->toArray(), 4);
    }

    /**
     * @throws ReflectionException
     */
    private function generateApis(string $authTokenName, string $authTokenDefaultValue): array
    {
        $tags = [];
        $paths = [];
        $components = [];

        $methods = $this->methodSpecCollection->getAllMethods();
        foreach ($methods as $version => $methodsArray) {
            foreach ($methodsArray as $name => $method) {
                if ($method->isIgnoreInSwagger()) {
                    continue;
                }

                $tag = new Tag($name, $method->getSummary());
                $tags[] = $tag;

                $parameters = [];

                $globalRequest = new Schema(sprintf('%sMainRequest', $name));
                $globalRequest->addProperty(
                    new SchemaProperty(name: 'jsonrpc', type: 'string', default: '2.0', example: '2.0')
                );
                $globalRequest->addRequired(
                    new SchemaProperty(name: 'jsonrpc', type: 'string', default: '2.0', example: '2.0')
                );
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
                    $prop = new SchemaProperty($requiredParameter['name'], $requiredParameter['type']);
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
                    $prop = new SchemaProperty($parameter['name'], $parameter['type']);
                    $requestSchema->addProperty($prop);
                }
                unset($parameters);

                $components[] = $requestSchema;

                $globalRequest->addProperty(new SchemaProperty(name: 'params', ref: sprintf('%sRequest', $name)));
                $globalRequest->addRequired(new SchemaProperty(name: 'params', ref: sprintf('%sRequest', $name)));
                if ($addIdToGlobalRequest) {
                    $globalRequest->addProperty(
                        new SchemaProperty(name: 'id', type: 'int', default: '0', example: '0')
                    );
                    $globalRequest->addRequired(
                        new SchemaProperty(name: 'id', type: 'int', default: '0', example: '0')
                    );
                }

                $components[] = $globalRequest;

                $requestBody = new RequestBody(sprintf('%sMainRequest', $name));

                $methodRef = new ReflectionClass($method->getMethodClass());
                $callMethod = $methodRef->getMethod('call');
                $responseClassRef = new ReflectionClass($callMethod->getReturnType()?->getName());
                $responseProperties = $responseClassRef->getProperties();

                $responseSchema = new Schema(sprintf('%sResponse', $responseClassRef->getShortName()));
                $responseSchema->addProperty(
                    new SchemaProperty(name: 'jsonrpc', type: 'string', default: '2.0', example: '2.0')
                );
                $responseSchema->addRequired(
                    new SchemaProperty(name: 'jsonrpc', type: 'string', default: '2.0', example: '2.0')
                );

                foreach ($responseProperties as $responseProperty) {
                    $respProp = new SchemaProperty(
                        $responseProperty->getName(), $responseProperty->getType()->getName()
                    );
                    $responseSchema->addProperty($respProp);
                    $responseSchema->addRequired($respProp);
                }
                $components[] = $responseSchema;

                if ($addIdToGlobalRequest) {
                    $responseSchema->addProperty(
                        new SchemaProperty(name: 'id', type: 'int', default: '0', example: '0')
                    );
                    $responseSchema->addRequired(
                        new SchemaProperty(name: 'id', type: 'int', default: '0', example: '0')
                    );
                }

                $response = new Response('200', sprintf('%sResponse', $responseClassRef->getShortName()));

                $path = new Path(
                    name: '#' . $this->camelToSnake($method->getMethodName(), '_'),
                    methodType: $method->getRequestType(),
                    summary: $method->getSummary(),
                    description: $method->getDescription(),
                    requestBody: $requestBody,
                    tags: [$tag],
                    responses: [$response],
                    parameters: [
                        [
                            'in' => 'header',
                            'name' => $authTokenName,
                            'schema' => ['type' => 'string'],
                            'default' => $authTokenDefaultValue
                        ]
                    ],
                );
                $paths[] = $path;
            }
        }

        return [$tags, $paths, $components];
    }

    private function camelToSnake(string $string, string $us = "-"): string
    {
        return strtolower(preg_replace('/(?<=\d)(?=[A-Za-z])|(?<=[A-Za-z])(?=\d)|(?<=[a-z])(?=[A-Z])/', $us, $string));
    }
}