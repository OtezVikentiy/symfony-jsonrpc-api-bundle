<?php

namespace OV\JsonRPCAPIBundle\Swagger;

final readonly class Path
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

        $data = [
            'parameters' => $this->parameters,
            'summary' => $this->summary,
            'description' => $this->description,
            'requestBody' => $this->requestBody->toArray(),
            'responses' => $responses
        ];

        if (!empty($this->tags)) {
            $data['tags'] = $this->tags;
        }

        return [
            mb_strtolower($this->methodType) => $data,
        ];
    }
}