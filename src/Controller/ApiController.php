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
use OV\JsonRPCAPIBundle\Core\Response\{ErrorResponse, OvResponseInterface};
use OV\JsonRPCAPIBundle\Core\Services\{
    RequestHandler,
    RequestHandler\BatchStrategyFactory,
    RequestRawDataHandler,
    ResponseService
};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

final class ApiController extends AbstractController
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
        RequestRawDataHandler $requestRawDataHandler,
        ResponseService $responseService,
    ): OvResponseInterface {
        try {
            $data = $requestRawDataHandler->prepareData($request);

            if (empty($data)) {
                throw new JRPCException('Invalid Request.', JRPCException::INVALID_REQUEST);
            }
        } catch (JRPCException|Throwable $e) {
            return $responseService->prepareJsonResponse(data: new ErrorResponse(error: $e, id: $data['id'] ?? null));
        }

        return $requestHandler->applyStrategy(
            BatchStrategyFactory::createBatchStrategy($data),
            $data,
            $requestRawDataHandler->getVersion($request),
            $request->getMethod()
        );
    }
}