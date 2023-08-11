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

use OV\JsonRPCAPIBundle\Core\{BaseRequest, BaseResponse, ErrorResponse, JRPCException};
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;

class ApiController extends AbstractController
{
    #[Route('/api/v{version<\d+>}', name: 'ov_json_rpc_api_index', methods: ['POST'])]
    public function index(
        Request $request,
        MethodSpecCollection $specCollection,
        ValidatorInterface $validator,
        Container $container
    ): JsonResponse {
        $baseRequest = null;
        try {
            $baseRequest = new BaseRequest($request->toArray());

            $method = $specCollection->getMethodSpec($baseRequest->getMethod());

            $requestClass      = $method->getRequest();
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
            foreach ($method->getValidators() as $field => $validatorItem) {
                $validators[$field] = new Assert\Type($validatorItem);
            }

            $violations = $validator->validate(
                $baseRequest->getParams() + ['id' => $baseRequest->getId()],
                new Assert\Collection($validators)
            );

            if ($violations->count()) {
                $errs = [];

                foreach ($violations as $violation) {
                    $errs[] = sprintf('%s - %s', $violation->getPropertyPath(), $violation->getMessage());
                }

                return $this->json(
                    new ErrorResponse(
                        JRPCException::INVALID_PARAMS,
                        'Invalid params',
                        $baseRequest->getId() ?? null,
                        implode(PHP_EOL, $errs)
                    )
                );
            }

            $processorClass = $method->getMethodClass();
            $processor      = $container->get($processorClass);

            $result   = $processor->call($requestInstance);
            $response = new BaseResponse($result);
        } catch (JRPCException $e) {
            return $this->json(
                new ErrorResponse(
                    $e->getCode(),
                    $e->getMessage(),
                    $baseRequest?->getId() ?? null,
                    $e->getAdditionalInfo(),
                )
            );
        } catch (Throwable $e) {
            return $this->json(
                new ErrorResponse(
                    JRPCException::INTERNAL_ERROR,
                    $e->getMessage(),
                    $baseRequest?->getId() ?? null,
                )
            );
        }

        return $this->json($response);
    }
}