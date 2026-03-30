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
        $result = [
            'title' => $this->title,
            'description' => $this->description,
            'termsOfService' => $this->termsOfService,
            'version' => $this->version,
        ];

        if ($this->contact !== null) {
            $contactArray = array_filter([
                'name' => $this->contact->getName(),
                'url' => $this->contact->getUrl(),
                'email' => $this->contact->getEmail(),
            ], fn($v) => $v !== '' && $v !== null);

            if (!empty($contactArray)) {
                $result['contact'] = $contactArray;
            }
        }

        if ($this->license !== null) {
            $licenseArray = array_filter([
                'name' => $this->license->getName(),
                'url' => $this->license->getUrl(),
            ], fn($v) => $v !== '' && $v !== null);

            if (!empty($licenseArray)) {
                $result['license'] = $licenseArray;
            }
        }

        return $result;
    }
}
