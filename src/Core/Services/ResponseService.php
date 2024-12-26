<?php

namespace OV\JsonRPCAPIBundle\Core\Services;

use OV\JsonRPCAPIBundle\Core\Response\BaseJsonResponseInterface;
use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use Symfony\Component\HttpFoundation\JsonResponse as SymfonyJsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final readonly class ResponseService
{
    private Serializer $serializer;

    public function __construct(
        private HeadersPreparer $headersPreparer,
    ) {
        $encoders = [new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];
        $this->serializer = new Serializer($normalizers, $encoders);
    }

    public function prepareJsonResponse(BaseJsonResponseInterface $data): JsonResponse
    {

        $json = $this->serializer->serialize($data, 'json', [
            'json_encode_options' => SymfonyJsonResponse::DEFAULT_ENCODING_OPTIONS,
        ]);

        return new JsonResponse($json, Response::HTTP_OK, $this->headersPreparer->prepareHeaders(), true);
    }
}