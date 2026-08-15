<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Core\Services;

use JsonException;
use OV\JsonRPCAPIBundle\Core\JRPCException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
final class RequestRawDataHandler
{
    private const CONTENT_TYPE_JSON = 'application/json';
    private const CONTENT_TYPE_MULTIPART = 'multipart/form-data';
    private const CONTENT_TYPE_SEPARATOR = ';';
    private const MEDIA_TYPE_PADDING = " \t";
    private const SERVER_QUERY_STRING_KEY = 'QUERY_STRING';

    /**
     * The one form field that is not a file: it carries the whole JSON-RPC request object, scalar
     * params included. Mapping arbitrary form fields onto params by name would bring back the
     * untyped-transport problem the bundle only tolerates for GET - a form field is a string, so
     * "42" and 42 become indistinguishable again - and would force a precedence rule for a key that
     * appears both as a part and inside params.
     */
    private const MULTIPART_ENVELOPE_FIELD = 'jsonrpc';

    private const PARAMS_FIELD = 'params';

    public function __construct(
        private readonly int $maxPayloadBytes = 1048576,
        private readonly int $maxJsonDepth = 64,
        private readonly bool $multipartEnabled = false,
        private readonly int $multipartMaxFiles = 10,
    ) {
    }

    /**
     * Whether this request arrived as multipart/form-data.
     *
     * Public because the method-level `acceptsMultipart` gate lives in RequestHandler, which sees
     * the decoded envelope rather than the Request: by the time a method has been resolved the
     * transport is no longer visible, so the controller reads it here once and passes it down.
     */
    public function isMultipartRequest(Request $request): bool
    {
        return $this->mediaType($request) === self::CONTENT_TYPE_MULTIPART;
    }

    public function getVersion(Request $request): int
    {
        $pathArray = explode('/', $request->getPathInfo());

        return (int) preg_replace('/\D+/', '', $pathArray[count($pathArray) - 1]);
    }

    /**
     * @throws JRPCException
     */
    public function prepareData(Request $request): array
    {
        if ($request->getMethod() === Request::METHOD_GET) {
            $rawQueryString = $request->server->get(self::SERVER_QUERY_STRING_KEY);
            $queryString = is_string($rawQueryString) ? $rawQueryString : '';

            if (strlen($queryString) > $this->maxPayloadBytes) {
                throw new JRPCException(
                    'Invalid Request.',
                    JRPCException::INVALID_REQUEST,
                    sprintf('Query string size exceeds limit of %d bytes.', $this->maxPayloadBytes),
                );
            }

            $queryData = $request->query->all();

            if ($this->arrayDepth($queryData) > $this->maxJsonDepth) {
                throw new JRPCException(
                    'Invalid Request.',
                    JRPCException::INVALID_REQUEST,
                    sprintf('Query nesting depth exceeds limit of %d.', $this->maxJsonDepth),
                );
            }

            return $queryData;
        }

        if (!in_array($request->getMethod(), [Request::METHOD_POST, Request::METHOD_DELETE, Request::METHOD_PUT, Request::METHOD_PATCH], true)) {
            throw new JRPCException(sprintf('Method %s not supported', $request->getMethod()), JRPCException::INVALID_REQUEST);
        }

        // Ahead of the empty-body check, because for a multipart request there is no body left to
        // read: PHP consumes php://input into $_POST/$_FILES, so getContent() is the empty string
        // and the request would be refused before its content type was ever considered.
        if ($this->multipartEnabled && $this->isMultipartRequest($request)) {
            return $this->prepareMultipartData($request);
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
            $jsonData = json_decode($requestContent, true, max(1, $this->maxJsonDepth), JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new JRPCException('Parse error.', JRPCException::PARSE_ERROR, $e->getMessage());
        }

        if (!is_array($jsonData)) {
            // Well-formed JSON that is not a Request object (e.g. a bare
            // number, string or boolean) is an Invalid Request per spec
            // section 5.1, not a parse failure.
            throw new JRPCException('Invalid Request.', JRPCException::INVALID_REQUEST);
        }

        return $jsonData;
    }

    /**
     * Turns a multipart request into the ordinary envelope array the rest of the bundle already
     * understands, with UploadedFile instances sitting in `params` next to the scalar parameters.
     *
     * Everything multipart-specific ends here: processBatch() and hydration below it stay unaware
     * of the transport, the same way they are unaware that a GET payload came from a query string.
     *
     * @throws JRPCException
     */
    private function prepareMultipartData(Request $request): array
    {
        // POST only, and said out loud rather than left to emerge: PHP populates $_POST and $_FILES
        // for a POST body alone, so a multipart PUT would otherwise be refused for the envelope it
        // appears not to carry - a message describing a symptom instead of the rule.
        if ($request->getMethod() !== Request::METHOD_POST) {
            throw new JRPCException(
                'Invalid Request.',
                JRPCException::INVALID_REQUEST,
                sprintf('%s is supported for POST requests only.', self::CONTENT_TYPE_MULTIPART),
            );
        }

        $envelope = $request->request->all()[self::MULTIPART_ENVELOPE_FIELD] ?? null;

        if (!is_string($envelope)) {
            throw new JRPCException(
                'Invalid Request.',
                JRPCException::INVALID_REQUEST,
                sprintf('A multipart request must carry the JSON-RPC request object in the "%s" field.', self::MULTIPART_ENVELOPE_FIELD),
            );
        }

        if (strlen($envelope) > $this->maxPayloadBytes) {
            throw new JRPCException(
                'Invalid Request.',
                JRPCException::INVALID_REQUEST,
                sprintf('Payload size exceeds limit of %d bytes.', $this->maxPayloadBytes),
            );
        }

        try {
            $decoded = json_decode($envelope, true, max(1, $this->maxJsonDepth), JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new JRPCException('Parse error.', JRPCException::PARSE_ERROR, $e->getMessage());
        }

        if (!is_array($decoded)) {
            throw new JRPCException('Invalid Request.', JRPCException::INVALID_REQUEST);
        }

        // A batch is a list, and a file part carries one name - there is no way to say which member
        // of a batch it belongs to. Refusing is the only honest answer; a batch that needs files is
        // several requests.
        if ($decoded !== [] && array_is_list($decoded)) {
            throw new JRPCException(
                'Invalid Request.',
                JRPCException::INVALID_REQUEST,
                'Batch requests are not supported over multipart/form-data.',
            );
        }

        return $this->mergeUploadedFiles($decoded, $request->files->all());
    }

    /**
     * @param array<string, mixed> $files
     *
     * @throws JRPCException
     */
    private function mergeUploadedFiles(array $decoded, array $files): array
    {
        if (count($files) > $this->multipartMaxFiles) {
            throw new JRPCException(
                'Invalid Request.',
                JRPCException::INVALID_REQUEST,
                sprintf('File count %d exceeds limit %d.', count($files), $this->multipartMaxFiles),
            );
        }

        if ($files === []) {
            return $decoded;
        }

        $params = $decoded[self::PARAMS_FIELD] ?? [];

        if (!is_array($params)) {
            throw new JRPCException('Invalid Request.', JRPCException::INVALID_REQUEST);
        }

        // A file part is named, so the parameters it joins have to be named too. Merging a name into
        // a by-position list would quietly turn it into a by-name object and drop every positional
        // argument's meaning, which is worse than saying the combination is not supported.
        if ($params !== [] && array_is_list($params)) {
            throw new JRPCException(
                'Invalid Request.',
                JRPCException::INVALID_REQUEST,
                'File parameters require by-name params.',
            );
        }

        foreach ($files as $name => $file) {
            // Nested parts - `photos[]` or `photo[thumb]` - arrive as arrays. Files live at the top
            // level of params only, so there is nothing sensible to map those onto.
            if (!$file instanceof UploadedFile) {
                throw new JRPCException(
                    'Invalid Request.',
                    JRPCException::INVALID_REQUEST,
                    sprintf('File part "%s" must be a single file at the top level.', (string) $name),
                );
            }

            if (array_key_exists((string) $name, $params)) {
                throw new JRPCException(
                    'Invalid Request.',
                    JRPCException::INVALID_REQUEST,
                    sprintf('File part "%s" collides with a parameter of the same name.', (string) $name),
                );
            }

            // Deliberately no size check here. By the time PHP populates $_FILES the upload is
            // already complete and written to disk, so rejecting it now saves nothing that
            // upload_max_filesize did not already save - and it would be a second, worse
            // implementation of Assert\File, which reports the size, the limit and the PHP upload
            // error with a message for each. multipart.max_file_bytes is applied there, as a
            // constraint on the parameter, where it also names the field it is talking about.
            $params[(string) $name] = $file;
        }

        $decoded[self::PARAMS_FIELD] = $params;

        return $decoded;
    }

    private function arrayDepth(array $data): int
    {
        $maxDepth = 1;

        foreach ($data as $value) {
            if (!is_array($value)) {
                continue;
            }

            $depth = $this->arrayDepth($value) + 1;
            if ($depth > $maxDepth) {
                $maxDepth = $depth;
            }
        }

        return $maxDepth;
    }

    /**
     * @throws JRPCException
     */
    private function assertJsonContentType(Request $request): void
    {
        if ($this->mediaType($request) !== self::CONTENT_TYPE_JSON) {
            throw new JRPCException(
                'Invalid Request.',
                JRPCException::INVALID_REQUEST,
                sprintf('Content-Type must be %s.', self::CONTENT_TYPE_JSON),
            );
        }
    }

    /**
     * The media type of a request, lowercased and stripped of its parameters.
     *
     * Trimmed for whitespace only. trim()'s default character list also includes NUL, so
     * "application/json\0" - a header no HTTP client produces, but one a hand-written or
     * proxied request can carry - was accepted as if it were the real thing. Nothing here should
     * be forgiving about bytes that cannot legitimately appear in a media type.
     */
    private function mediaType(Request $request): string
    {
        $contentType = (string) $request->headers->get('Content-Type');

        return strtolower(trim(explode(self::CONTENT_TYPE_SEPARATOR, $contentType)[0], self::MEDIA_TYPE_PADDING));
    }
}
