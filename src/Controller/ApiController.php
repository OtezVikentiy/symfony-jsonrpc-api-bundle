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
use OV\JsonRPCAPIBundle\Core\Response\{ErrorResponse, JsonResponse, OvResponseInterface, PlainResponseInterface};
use OV\JsonRPCAPIBundle\Core\Services\{
    HeadersPreparer,
    RequestHandler,
    RequestHandler\BatchStrategyFactory,
    RequestRawDataHandler
};
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

final class ApiController extends BaseController
{
    #[Route(
        path: '/api/v{version<\d+>}',
        name: 'ov_json_rpc_api_index',
        methods: [
            Request::METHOD_POST,
            Request::METHOD_GET,
            Request::METHOD_PUT,
            Request::METHOD_PATCH,
            Request::METHOD_DELETE
        ]
    )]
    public function index(
        Request $request,
        RequestHandler $requestHandler,
        HeadersPreparer $headersPreparer,
        RequestRawDataHandler $requestRawDataHandler,
    ): OvResponseInterface {
        try {
            $data = $requestRawDataHandler->prepareData($request);

            if (empty($data)) {
                throw new JRPCException('Invalid Request.', JRPCException::INVALID_REQUEST);
            }
        } catch (JRPCException|Throwable $e) {
            return $this->json(
                data: new ErrorResponse(error: $e, id: $data['id'] ?? null),
                headers: $headersPreparer->prepareHeaders()
            );
        }

        $strategy = BatchStrategyFactory::createBatchStrategy($data);
        return $requestHandler->applyStrategy($strategy, $data, $requestRawDataHandler->getVersion($request), $request->getMethod());

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
}