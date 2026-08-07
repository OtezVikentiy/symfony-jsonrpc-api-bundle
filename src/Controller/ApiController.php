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

namespace OV\JsonRPCAPIBundle\Controller;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcCallLoggerInterface;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler\BatchStrategyFactory;
use OV\JsonRPCAPIBundle\Core\Services\RequestRawDataHandler;
use OV\JsonRPCAPIBundle\Core\Services\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

final class ApiController extends AbstractController
{
    private const HANDLED_JSON_RPC_METHODS = [
        Request::METHOD_POST,
        Request::METHOD_GET,
        Request::METHOD_PUT,
        Request::METHOD_PATCH,
        Request::METHOD_DELETE,
    ];

    #[Route(
        path: '/api/v{version<\d+>}',
        name: 'ov_json_rpc_api_index',
        methods: [...self::HANDLED_JSON_RPC_METHODS, Request::METHOD_OPTIONS],
    )]
    public function index(
        Request $request,
        RequestHandler $requestHandler,
        RequestRawDataHandler $requestRawDataHandler,
        ResponseService $responseService,
        JsonRpcCallLoggerInterface $callLogger,
    ): OvResponseInterface {
        if ($request->getMethod() === Request::METHOD_OPTIONS) {
            return $responseService->preparePreflightResponse(self::HANDLED_JSON_RPC_METHODS);
        }

        try {
            $data = $requestRawDataHandler->prepareData($request);

            if (empty($data)) {
                throw new JRPCException('Invalid Request.', JRPCException::INVALID_REQUEST);
            }
        } catch (Throwable $e) {
            $call = $callLogger->logRawRequest((string) $request->getContent());
            $errResp = $responseService->prepareErrorResponse(
                $e,
                null,
            );
            $callLogger->logResponse($call, $errResp);

            return $errResp;
        }

        try {
            return $requestHandler->applyStrategy(
                BatchStrategyFactory::createBatchStrategy($data),
                $data,
                $requestRawDataHandler->getVersion($request),
                $request->getMethod()
            );
        } catch (Throwable $e) {
            $call = $callLogger->logRawRequest((string) $request->getContent());
            $errResp = $responseService->prepareErrorResponse($e, $data['id'] ?? null);
            $callLogger->logResponse($call, $errResp);

            return $errResp;
        }
    }
}
