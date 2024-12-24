<?php

namespace OV\JsonRPCAPIBundle\Controller;

use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

abstract class BaseController extends AbstractController
{
    protected function json(mixed $data, int $status = 200, array $headers = [], array $context = []): JsonResponse
    {
        if ($this->container->has('serializer')) {
            $json = $this->container->get('serializer')->serialize($data, 'json', array_merge([
                'json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS,
            ], $context));

            return new JsonResponse($json, $status, $headers, true);
        }

        return new JsonResponse($data, $status, $headers);
    }
}