<?php

namespace OV\JsonRPCAPIBundle\Core\Services;

use JsonException;
use OV\JsonRPCAPIBundle\Core\JRPCException;
use Symfony\Component\HttpFoundation\Request;

final class RequestRawDataHandler
{
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
            $data = $request->query->all();
        } elseif (in_array($request->getMethod(), [Request::METHOD_POST, Request::METHOD_DELETE, Request::METHOD_PUT, Request::METHOD_PATCH])) {
            $requestData = [];
            if (!empty($request->request->all())) {
                $requestData = $request->request->all();
            }
            $jsonData = [];
            $requestContent = $request->getContent();
            if (!empty($requestContent)) {
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
            }
            $data = array_merge($requestData, $jsonData);
        } else {
            throw new JRPCException(sprintf('Method %s not supported', $request->getMethod()), JRPCException::INVALID_REQUEST);
        }

        return $data;
    }
}