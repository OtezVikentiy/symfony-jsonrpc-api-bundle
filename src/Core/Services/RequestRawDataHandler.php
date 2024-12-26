<?php

namespace OV\JsonRPCAPIBundle\Core\Services;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use Symfony\Component\HttpFoundation\Request;

final class RequestRawDataHandler
{
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
                $jsonData = json_decode($requestContent, true);
                if (is_null($jsonData)) {
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