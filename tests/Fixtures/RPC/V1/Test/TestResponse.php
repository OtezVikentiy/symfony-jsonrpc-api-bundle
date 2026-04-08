<?php
/** @noinspection PhpUnused */

/** @noinspection PhpUnused */

/** @noinspection PhpUnused */

/** @noinspection PhpUnused */

/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\RPC\V1\Test;

use OV\JsonRPCAPIBundle\Core\Annotation\SwaggerArrayProperty;
use OV\JsonRPCAPIBundle\Core\Annotation\SwaggerProperty;

final class TestResponse
{
    #[SwaggerProperty(default: true, example: true)]
    private bool $success;

    #[SwaggerProperty(default: '', example: 'iphone 12', format: '/[A-Za-z0-9]+/]/')]
    private string $title;

    private ?TestRequest $request = null;

    #[SwaggerArrayProperty(type: Test::class, ofClass: true)]
    private array $tests = [];

    #[SwaggerArrayProperty(type: 'string')]
    private array $errors = [];

    public function __construct(string $title, bool $success = true, array $errors = [])
    {
        $this->success = $success;
        $this->title   = $title;
        $this->errors = $errors;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    public function getRequest(): ?TestRequest
    {
        return $this->request;
    }

    public function setRequest(?TestRequest $request): TestResponse
    {
        $this->request = $request;

        return $this;
    }

    public function getTests(): array
    {
        return $this->tests;
    }

    public function setTests(array $tests): TestResponse
    {
        $this->tests = $tests;

        return $this;
    }
    
    public function addTest(Test $test): TestResponse
    {
        $this->tests[] = $test;
        
        return $this;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function setErrors(array $errors): TestResponse
    {
        $this->errors = $errors;

        return $this;
    }

    public function addError(string $error): TestResponse
    {
        $this->errors[] = $error;

        return $this;
    }
}