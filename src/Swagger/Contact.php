<?php

namespace OV\JsonRPCAPIBundle\Swagger;

readonly class Contact
{
    public function __construct(
        private string $name = '',
        private string $url = '',
        private string $email = '',
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}