<?php
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\Controller;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Response\ErrorResponse;
use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;
use OV\JsonRPCAPIBundle\Core\Response\PlainResponseInterface;
use OV\JsonRPCAPIBundle\Core\Services\HeadersPreparer;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

final class ApiController extends BaseController
{
    public function __construct(
    ) {
    }

    #[Route('/api/v{version<\d+>}', name: 'ov_json_rpc_api_index', methods: ['POST', 'GET', 'PUT', 'PATCH', 'DELETE'])]
    public function index(
        Request $request,
        RequestHandler $requestHandler,
        HeadersPreparer $headersPreparer
    ): OvResponseInterface {
        try {
            $data = $this->prepareData($request);

            if (empty($data)) {
                throw new JRPCException('Invalid Request.', JRPCException::INVALID_REQUEST);
            }

            $batches = $data;
            if (!$this->isBatch($data)) {
                $batches = [$data];
            }
        } catch (JRPCException|Throwable $e) {
            return $this->json(
                data: new ErrorResponse(error: $e, id: $data['id'] ?? null),
                headers: $headersPreparer->prepareHeaders()
            );
        }

        $responses = [];
        foreach ($batches as $batch) {
            $responses[] = $requestHandler->processBatch($batch, $this->getVersion($request), $request->getMethod());
        }

        $responses = array_values(array_filter($responses, fn($item) => !is_null($item)));

        if (empty($responses)) {
            return new JsonResponse(headers: $headersPreparer->prepareHeaders());
        }

        if (count($responses) === 1 && $responses[0] instanceof PlainResponseInterface) {
            return $responses[0];
        }

        $data = (count($responses) > 1) ? $responses : $responses[0];

        return $this->json(data: $data, headers: $headersPreparer->prepareHeaders());
    }

    private function getVersion(Request $request): int
    {
        $pathArray = explode('/', $request->getPathInfo());

        return (int)preg_replace('/\D+/', '', $pathArray[count($pathArray) - 1]);
    }

    private function prepareData(Request $request): array
    {
        $data = [];

        if ($request->getMethod() === 'GET') {
            $data = $request->query->all();
        } elseif (in_array($request->getMethod(), ['POST', 'DELETE', 'PUT', 'PATCH'])) {
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
        }

        return $data;
    }

    private function isBatch(array $data): bool
    {
        if (
            isset($data[0])
            && isset($data[1])
            && is_array($data[0])
            && is_array($data[1])
            && array_key_exists('jsonrpc', $data[0])
            && array_key_exists('jsonrpc', $data[1])
        ) {
            return true;
        }

        return false;
    }
}