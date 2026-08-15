<?php

declare(strict_types=1);
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\DependencyInjection;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal not final: CompilerPass registers this as a lazy service on PHP 8.3+,
 * and Symfony's native lazy ghost proxies require a non-final class to extend.
 */
class MethodSpec
{
    /** @var array<string, Constraint> */
    private array $compiledValidators;

    /**
     * @param class-string $methodClass
     */
    public function __construct(
        private readonly string $methodClass,
        private readonly string $requestType,
        private readonly string $methodName,
        private readonly RequestMetadata $requestMetadata,
        private readonly SwaggerMetadata $swaggerMetadata,
        private readonly array $roles = [],
        private readonly bool $plainResponse = false,
        private readonly bool $preProcessorExists = false,
        private readonly bool $postProcessorExists = false,
        private readonly bool $allowExtraFields = false,
        private readonly bool $acceptsMultipart = false,
        private readonly int|string|null $maxFileBytes = null,
    ) {
    }

    /**
     * @return class-string
     */
    public function getMethodClass(): string
    {
        return $this->methodClass;
    }

    public function getRequestType(): string
    {
        return $this->requestType;
    }

    /** @noinspection PhpUnused */
    public function getMethodName(): string
    {
        return $this->methodName;
    }

    public function getRequestMetadata(): RequestMetadata
    {
        return $this->requestMetadata;
    }

    public function getSwaggerMetadata(): SwaggerMetadata
    {
        return $this->swaggerMetadata;
    }

    /** @noinspection PhpUnused */
    public function getSummary(): string
    {
        return $this->swaggerMetadata->getSummary();
    }

    /** @noinspection PhpUnused */
    public function getDescription(): string
    {
        return $this->swaggerMetadata->getDescription();
    }

    /** @noinspection PhpUnused */
    public function isIgnoreInSwagger(): bool
    {
        return $this->swaggerMetadata->isIgnoreInSwagger();
    }

    /** @noinspection PhpUnused */
    public function getTags(): ?array
    {
        return $this->swaggerMetadata->getTags();
    }

    public function getGroup(): ?string
    {
        return $this->swaggerMetadata->getGroup();
    }

    public function getRequest(): ?string
    {
        return $this->requestMetadata->getRequest();
    }

    public function getAllParameters(): array
    {
        return $this->requestMetadata->getAllParameters();
    }

    public function getRequiredParameters(): array
    {
        return $this->requestMetadata->getRequiredParameters();
    }

    public function getRequestGetters(): array
    {
        return $this->requestMetadata->getRequestGetters();
    }

    public function getRequestSetters(): array
    {
        return $this->requestMetadata->getRequestSetters();
    }

    public function getRequestAdders(): array
    {
        return $this->requestMetadata->getRequestAdders();
    }

    public function getValidators(): array
    {
        return $this->requestMetadata->getValidators();
    }

    /**
     * Symfony constraints are immutable and expensive to construct via reflection,
     * so the compiled set is built once per method spec and reused across requests.
     *
     * @return array<string, Constraint>
     */
    public function getCompiledValidators(): array
    {
        return $this->compiledValidators ??= $this->compileValidators();
    }

    /**
     * @return array<string, Constraint>
     */
    private function compileValidators(): array
    {
        $compiled = [];
        foreach ($this->requestMetadata->getValidators() as $field => $validatorItem) {
            $constraint = $this->constraintForType($validatorItem['type']);

            $compiled[$field] = $validatorItem['allowsNull'] === false
                ? $constraint
                : new Assert\Optional([
                    new Assert\AtLeastOneOf([
                        $constraint,
                        new Assert\Blank(),
                        new Assert\IsNull(),
                    ]),
                ]);
        }

        return $compiled;
    }

    /**
     * The envelope check for one declared type.
     *
     * For everything but a file this is the Assert\Type it has always been. A file gets Assert\File
     * behind it, which is where every rule about an upload already lives: the eight PHP upload error
     * codes with a message each, the size limit with its own suffix formatting, and the empty /
     * missing / unreadable cases. None of that is worth writing again here, and the version written
     * here would be the one that goes stale.
     *
     * Sequentially rather than a pair of constraints, because the order matters in both directions.
     * Assert\File accepts a string path to an existing file - it is written for form data, where a
     * file field may legitimately arrive as a path - so a caller sending `{"file": "/etc/passwd"}`
     * over plain JSON would satisfy it. Assert\Type has to answer first, and Sequentially is what
     * stops the second constraint once the first has spoken.
     */
    private function constraintForType(string $type): Constraint
    {
        $typeConstraint = new Assert\Type($type);

        if (!is_a($type, UploadedFile::class, true)) {
            return $typeConstraint;
        }

        // maxSize null means "no limit of ours"; PHP's own upload_max_filesize / post_max_size
        // still apply, and UploadedFile::getMaxFilesize() is what reports them.
        $maxSize = $this->maxFileBytes;
        if (is_int($maxSize) && $maxSize < 1) {
            $maxSize = null;
        }

        return new Assert\Sequentially([
            $typeConstraint,
            new Assert\File(maxSize: $maxSize),
        ]);
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function isPlainResponse(): bool
    {
        return $this->plainResponse;
    }

    public function isPreProcessorExists(): bool
    {
        return $this->preProcessorExists;
    }

    public function isPostProcessorExists(): bool
    {
        return $this->postProcessorExists;
    }

    public function isAllowExtraFields(): bool
    {
        return $this->allowExtraFields;
    }

    public function isAcceptsMultipart(): bool
    {
        return $this->acceptsMultipart;
    }
}
