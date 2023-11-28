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

    private function generateSwaggerYaml(array $item)
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

        [$tags, $paths, $components] = $this->generateApis();

        $swagger = new Openapi($info, $servers, $tags, $paths, $components);

        return Yaml::dump($swagger->toArray(), 4);
    }

    private function generateApis(): array
    {
        $translateTypes = function (string $type) {
            $map = [
                'int' => 'integer',
                'bool' => 'boolean',
            ];
            return $map[$type] ?? $type;
        };

        $tags = [];
        $paths = [];
        $components = [];

        $methods = $this->methodSpecCollection->getAllMethods();
        foreach ($methods as $name => $method) {
            if ($method->isIgnoreInSwagger()) continue;

            $tag = new Tag($name, $method->getSummary());
            $tags[] = $tag;

            $parameters = [];
            $requestSchema = new Schema(sprintf('%sRequest', $name));
            foreach ($method->getRequiredParameters() as $requiredParameter) {
                $prop = new SchemaProperty($requiredParameter['name'], $translateTypes($requiredParameter['type']));
                $requestSchema->addProperty($prop);
                $requestSchema->addRequired($prop);
                $parameters[$requiredParameter['name']] = $requiredParameter['name'];
            }
            foreach ($method->getAllParameters() as $parameter) {
                if (isset($parameters[$parameter['name']])) continue;
                $prop = new SchemaProperty($parameter['name'], $translateTypes($parameter['type']));
                $requestSchema->addProperty($prop);
            }
            unset($parameters);
            $components[] = $requestSchema;
            $requestBody = new RequestBody(sprintf('%sRequest', $name));

            $methodRef = new ReflectionClass($method->getMethodClass());
            $callMethod = $methodRef->getMethod('call');
            $responseClassRef = new ReflectionClass($translateTypes($callMethod->getReturnType()?->getName()));
            $responseProperties = $responseClassRef->getProperties();
            $responseSchema = new Schema(sprintf('%sResponse', $responseClassRef->getShortName()));
            foreach ($responseProperties as $responseProperty) {
                $respProp = new SchemaProperty($responseProperty->getName(), $translateTypes($responseProperty->getType()->getName()));
                $responseSchema->addProperty($respProp);
                $responseSchema->addRequired($respProp);
            }
            $components[] = $responseSchema;
            $response = new Response('200', sprintf('%sResponse', $responseClassRef->getShortName()));

            $path = new Path(
                name: $method->getMethodName(),
                methodType: $method->getRequestType(),
                summary: $method->getSummary(),
                description: $method->getDescription(),
                requestBody: $requestBody,
                tags: [$tag],
                responses: [$response]
            );
            $paths[] = $path;
        }

        return [$tags, $paths, $components];
    }
}