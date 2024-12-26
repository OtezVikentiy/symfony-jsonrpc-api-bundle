<?php

namespace OV\JsonRPCAPIBundle\Core\Services;

use OV\JsonRPCAPIBundle\Core\Response\BaseJsonResponseInterface;
use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse as SymfonyJsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class ResponseService
{
    public function __construct(
        private ContainerInterface $container,
        private HeadersPreparer $headersPreparer,
    ) {
    }

    public function prepareJsonResponse(BaseJsonResponseInterface $data): JsonResponse
    {
        if ($this->container->has('serializer')) {
            $json = $this->container->get('serializer')->serialize($data, 'json', [
                'json_encode_options' => SymfonyJsonResponse::DEFAULT_ENCODING_OPTIONS,
            ]);

            return new JsonResponse($json, Response::HTTP_OK, $this->headersPreparer->prepareHeaders(), true);
        }

        return new JsonResponse($data, Response::HTTP_OK, $this->headersPreparer->prepareHeaders());
    }
}