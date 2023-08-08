<?php

namespace OV\JsonRPCAPIBundle\Controller;

use OV\JsonRPCAPIBundle\Core\JsonRPCAPIBaseRequest;
use OV\JsonRPCAPIBundle\Core\JsonRPCAPIErrorResponse;
use OV\JsonRPCAPIBundle\Core\JsonRPCAPIException;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;
use Symfony\Component\Validator\Constraints as Assert;

class OvJsonRpcApiController extends AbstractController
{
    public function __construct(
        private readonly MethodSpecCollection $specCollection,
        private readonly ValidatorInterface $validator
    ){
    }

    #[Route('/api/v{version<\d+>}', name: 'ov_json_rpc_api_index', methods: ['POST'])]
    public function index(
        Request $request
    ): JsonResponse {
        $baseRequest = null;
        try {
            $baseRequest = new JsonRPCAPIBaseRequest($request->toArray());

            $method = $this->specCollection->getMethodSpec($baseRequest->getMethod());

            $requestClass = $method->getRequest();
            $constructorParams = [];
            foreach ($method->getRequiredParameters() as $requiredParameter) {
                if ($requiredParameter === 'id') {
                    $constructorParams[] = $baseRequest->getId();
                    continue;
                }
                $constructorParams[] = $baseRequest->getParams()[$requiredParameter] ?? null;
            }

            $requestInstance = new $requestClass(...$constructorParams);

            foreach ($method->getAllParameters() as $allParameter) {
                $requestSetter = $method->getRequestSetters()[$allParameter] ?? null;
                if (!is_null($requestSetter)) {
                    $value = $allParameter === 'id' ? $baseRequest->getId() : $baseRequest->getParams()[$allParameter] ?? null;
                    $requestInstance->$requestSetter($value);
                }
            }

            $validators = [];
            foreach ($method->getValidators() as $field => $validator) {
                $validators[$field] = new Assert\Type($validator);
            }

            $violations = $this->validator->validate($baseRequest->getParams() + ['id' => $baseRequest->getId()], new Assert\Collection($validators));

            if ($violations->count()) {
                $errs = [];

                foreach ($violations as $violation) {
                    $errs[] = sprintf('%s - %s', $violation->getPropertyPath(), $violation->getMessage());
                }

                return $this->json(
                    new JsonRPCAPIErrorResponse(
                        JsonRPCAPIException::INVALID_PARAMS,
                        'Invalid params',
                        $baseRequest->getId() ?? null,
                        implode(PHP_EOL, $errs)
                    )
                );
            }

            $processorClass = $method->getMethodClass();
            $processor = new $processorClass();

            $response = $processor->call($requestInstance);
        } catch (JsonRPCAPIException $e) {
            return $this->json(new JsonRPCAPIErrorResponse(
                $e->getCode(),
                $e->getMessage(),
                $baseRequest?->getId() ?? null,
                $e->getAdditionalInfo(),
            ));
        } catch (Throwable $e) {
            return $this->json(new JsonRPCAPIErrorResponse(
                JsonRPCAPIException::INTERNAL_ERROR,
                $e->getMessage(),
                $baseRequest?->getId() ?? null,
            ));
        }

        return $this->json($response);
    }
}