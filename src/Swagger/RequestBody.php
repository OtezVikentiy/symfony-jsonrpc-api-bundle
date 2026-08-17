<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Swagger;

/**
 * @internal
 */
final readonly class RequestBody
{
    private const CONTENT_TYPE_JSON = 'application/json';
    private const CONTENT_TYPE_MULTIPART = 'multipart/form-data';
    private const ENVELOPE_FIELD = 'jsonrpc';
    private const ENVELOPE_DESCRIPTION_FORMAT = 'The JSON-RPC request object, serialized as a string. Same shape as %s: scalar parameters travel inside it, only files are sent as separate parts.';

    /**
     * @param array<int, array{name: string, required: bool}> $fileParts the file-typed parameters of a
     *                                                                    method that declares acceptsMultipart
     */
    public function __construct(
        private string $contentRef = '',
        private string $description = '',
        private array $fileParts = [],
        private bool $multipart = false,
    ) {
    }

    private function getContentRef(): array
    {
        return [
            self::CONTENT_TYPE_JSON => [
                'schema' => [
                    '$ref' => sprintf('#/components/schemas/%s', $this->contentRef),
                ],
            ],
        ];
    }

    /**
     * A method that accepts multipart is described by multipart alone, not by both content types.
     *
     * Its file parameters have no JSON representation at all, so an application/json body advertised
     * next to this one would describe a request no caller can actually send - and the parameters it
     * left out would be exactly the ones the method needs.
     */
    private function getMultipartContent(): array
    {
        $properties = [
            self::ENVELOPE_FIELD => [
                'type' => 'string',
                'description' => sprintf(self::ENVELOPE_DESCRIPTION_FORMAT, $this->contentRef),
            ],
        ];
        $required = [self::ENVELOPE_FIELD];

        foreach ($this->fileParts as $filePart) {
            $properties[$filePart['name']] = [
                'type' => 'string',
                'format' => 'binary',
            ];

            if ($filePart['required']) {
                $required[] = $filePart['name'];
            }
        }

        return [
            self::CONTENT_TYPE_MULTIPART => [
                'schema' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => $required,
                ],
            ],
        ];
    }

    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'content' => $this->multipart ? $this->getMultipartContent() : $this->getContentRef(),
        ];
    }
}
