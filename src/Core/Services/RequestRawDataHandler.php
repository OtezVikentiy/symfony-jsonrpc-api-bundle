<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Core\Services;

use JsonException;
use OV\JsonRPCAPIBundle\Core\JRPCException;
use Symfony\Component\HttpFoundation\Request;

final class RequestRawDataHandler
{
    private const CONTENT_TYPE_JSON = 'application/json';
    private const CONTENT_TYPE_SEPARATOR = ';';

    public function __construct(
        private readonly int $maxPayloadBytes = 1048576,
        private readonly int $maxJsonDepth = 64,
    ) {
    }

    public function getVersion(Request $request): int
    {
        $pathArray = explode('/', $request->getPathInfo());

        return (int)preg_replace('/\D+/', '', $pathArray[count($pathArray) - 1]);
    }

    /**
     * @throws JRPCException
     */
    public function prepareData(Request $request): array
    {
        if ($request->getMethod() === Request::METHOD_GET) {
            return $request->query->all();
        }

        if (!in_array($request->getMethod(), [Request::METHOD_POST, Request::METHOD_DELETE, Request::METHOD_PUT, Request::METHOD_PATCH], true)) {
            throw new JRPCException(sprintf('Method %s not supported', $request->getMethod()), JRPCException::INVALID_REQUEST);
        }

        $requestContent = $request->getContent();
        if (empty($requestContent)) {
            return [];
        }

        $this->assertJsonContentType($request);

        if (strlen($requestContent) > $this->maxPayloadBytes) {
            throw new JRPCException(
                'Invalid Request.',
                JRPCException::INVALID_REQUEST,
                sprintf('Payload size exceeds limit of %d bytes.', $this->maxPayloadBytes)
            );
        }

        try {
            $jsonData = json_decode($requestContent, true, $this->maxJsonDepth, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new JRPCException('Parse error.', JRPCException::PARSE_ERROR, $e->getMessage());
        }

        if (!is_array($jsonData)) {
            throw new JRPCException('Parse error.', JRPCException::PARSE_ERROR);
        }

        return $jsonData;
    }

    /**
     * @throws JRPCException
     */
    private function assertJsonContentType(Request $request): void
    {
        $contentType = (string) $request->headers->get('Content-Type');
        $mimeType = strtolower(trim(explode(self::CONTENT_TYPE_SEPARATOR, $contentType)[0]));

        if ($mimeType !== self::CONTENT_TYPE_JSON) {
            throw new JRPCException(
                'Invalid Request.',
                JRPCException::INVALID_REQUEST,
                sprintf('Content-Type must be %s.', self::CONTENT_TYPE_JSON),
            );
        }
    }
}