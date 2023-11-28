<?php

namespace OV\JsonRPCAPIBundle\Swagger;

class Info
{
    public function __construct(
        private readonly string $title = '',
        private readonly string $description = '',
        private readonly string $termsOfService = '',
        private readonly string $version = '',
        private readonly ?Contact $contact = null,
        private readonly ?License $license = null,
    ) {}

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'termsOfService' => $this->termsOfService,
            'version' => $this->version,
            'contact' => [
                'name' => $this->contact?->getName(),
                'url' => $this->contact?->getUrl(),
                'email' => $this->contact?->getEmail(),
            ],
            'license' => [
                'name' => $this->license?->getName(),
                'url' => $this->license?->getUrl(),
            ]
        ];
    }
}