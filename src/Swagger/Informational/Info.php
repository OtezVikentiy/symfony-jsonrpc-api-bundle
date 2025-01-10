<?php

namespace OV\JsonRPCAPIBundle\Swagger\Informational;

final readonly class Info
{
    public function __construct(
        private string $title = '',
        private string $description = '',
        private string $termsOfService = '',
        private string $version = '',
        private ?Contact $contact = null,
        private ?License $license = null,
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
            ],
        ];
    }
}