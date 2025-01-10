<?php

namespace OV\JsonRPCAPIBundle\Swagger;

final readonly class RequestBody
{
    public function __construct(
        private string $contentRef = '',
        private string $description = '',
    ) {}

    private function getContentRef(): array
    {
        return [
            'application/json' => [
                'schema' => [
                    '$ref' => sprintf('#/components/schemas/%s', $this->contentRef)
                ]
            ]
        ];
    }

    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'content' => $this->getContentRef()
        ];
    }
}