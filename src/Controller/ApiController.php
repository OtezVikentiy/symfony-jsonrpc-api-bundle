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
use OV\JsonRPCAPIBundle\Core\Request\BaseRequest;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler\BatchStrategyFactory;
use OV\JsonRPCAPIBundle\Core\Services\RequestRawDataHandler;
use OV\JsonRPCAPIBundle\Core\Services\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * @internal
 */
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
            // $data is the raw decoded payload, so its id is whatever the caller sent - an array or
            // an object included, and section 5 forbids echoing those. Nothing is known to reach
            // here today: processBatch() catches every Throwable including inside its finally, and a
            // batch is a list, so it has no id to begin with. The guard is kept because the value is
            // caller-controlled and the day this path does become reachable is not the day to
            // discover it reflects arbitrary structures back. Deliberately without a test - covering
            // it would mean dropping final from RequestHandler to double it, which is a worse trade.
            $rawId = $data['id'] ?? null;
            $errResp = $responseService->prepareErrorResponse($e, BaseRequest::isValidId($rawId) ? $rawId : null);
            $callLogger->logResponse($call, $errResp);

            return $errResp;
        }
    }
}
