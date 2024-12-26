<?php

namespace OV\JsonRPCAPIBundle\Swagger;

readonly class Path
{
    public function __construct(
        private string $name = '',
        private string $methodType = '',
        private string $summary = '',
        private string $description = '',
        private ?RequestBody $requestBody = null,
        private array $tags = [],
        private array $responses = [],
        private array $parameters = [],
        private string $operationId = '',
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function toArray(): array
    {
        $responses = [];
        foreach ($this->responses as $response) {
            $responses[$response->getCode()] = $response->toArray();
        }

        return [
            mb_strtolower($this->methodType) => [
                'parameters' => $this->parameters,
                'tags' => array_map(fn (Tag $tag) => $tag->getName(), $this->tags),
                'summary' => $this->summary,
                'description' => $this->description,
                'requestBody' => $this->requestBody->toArray(),
                'responses' => $responses
            ]
        ];
    }
}