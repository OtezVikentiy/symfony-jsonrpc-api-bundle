<?php

namespace OV\JsonRPCAPIBundle\Swagger;

class Contact
{
    public function __construct(
        private readonly string $name = '',
        private readonly string $url = '',
        private readonly string $email = '',
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