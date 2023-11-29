<?php

namespace OV\JsonRPCAPIBundle\Swagger;

class Path
{
    public function __construct(
        private readonly string $name = '',
        private readonly string $methodType = '',
        private readonly string $summary = '',
        private readonly string $description = '',
        private readonly ?RequestBody $requestBody = null,
        private readonly array $tags = [],
        private readonly array $responses = [],
        private readonly array $parameters = [],
        private readonly string $operationId = '',
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