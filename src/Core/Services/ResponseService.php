<?php

namespace OV\JsonRPCAPIBundle\Core\Services;

use OV\JsonRPCAPIBundle\Core\Response\BaseJsonResponseInterface;
use OV\JsonRPCAPIBundle\Core\Response\ErrorResponse;
use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use Symfony\Component\HttpFoundation\JsonResponse as SymfonyJsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class ResponseService
{
    public function __construct(
        private HeadersPreparer $headersPreparer,
        private ?ErrorSanitizer $errorSanitizer = null,
    ) {
    }

    public function prepareJsonResponse(BaseJsonResponseInterface $data): JsonResponse
    {
        $json = json_encode($data->toArray(), SymfonyJsonResponse::DEFAULT_ENCODING_OPTIONS);

        return new JsonResponse($json, Response::HTTP_OK, $this->headersPreparer->prepareHeaders(), true);
    }

    public function prepareErrorResponse(Throwable $error, mixed $id): JsonResponse
    {
        $sanitized = $this->errorSanitizer?->sanitize($error) ?? $error;

        return $this->prepareJsonResponse(new ErrorResponse(error: $sanitized, id: $id));
    }
}
