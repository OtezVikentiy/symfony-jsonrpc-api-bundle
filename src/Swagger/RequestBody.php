<?php

namespace OV\JsonRPCAPIBundle\Swagger;

class RequestBody
{
    public function __construct(
        private readonly string $contentRef = '',
        private readonly string $description = '',
    ) {}

    private function getContentRef()
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