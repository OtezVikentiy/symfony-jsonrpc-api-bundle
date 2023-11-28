<?php

namespace OV\JsonRPCAPIBundle\Swagger;

class Response
{
    public function __construct(
        private readonly string $code = '',
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

    public function getCode(): string
    {
        return $this->code;
    }
}