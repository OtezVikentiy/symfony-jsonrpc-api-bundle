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

use OV\JsonRPCAPIBundle\Core\{BaseRequest, BaseResponse, ErrorResponse, JRPCException, PlainResponseInterface};
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;

final class ApiController extends AbstractController
{
    public function __construct(
        private readonly array $accessControlAllowOriginList,
        private readonly Security $security,
        private readonly MethodSpecCollection $specCollection,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/v{version<\d+>}', name: 'ov_json_rpc_api_index', methods: ['POST', 'GET', 'PUT', 'PATCH', 'DELETE'])]
    public function index(Request $request, Container $container): JsonResponse|Response|PlainResponseInterface
    {
        try {
            $methodType = $request->getMethod();
            $pathArray = explode('/', $request->getPathInfo());
            $version = (int)preg_replace('/\D+/', '', $pathArray[count($pathArray) - 1]);
            $data = [];
            if ($methodType === 'GET') {
                $data = $request->query->all();
            } elseif (in_array($methodType, ['POST', 'DELETE', 'PUT', 'PATCH'])) {
                $requestData = [];
                if (!empty($request->request->all())) {
                    $requestData = $request->request->all();
                }
                $jsonData = [];
                $requestContent = $request->getContent();
                if (!empty($requestContent)) {
                    $jsonData = json_decode($requestContent, true);
                    if (is_null($jsonData)) {
                        throw new JRPCException('Parse error.', JRPCException::PARSE_ERROR);
                    }
                }
                $data = array_merge($requestData, $jsonData);
            }

            if (empty($data)) {
                throw new JRPCException('Invalid Request.', JRPCException::INVALID_REQUEST);
            }

            $batches = $data;
            if (!$this->isBatch($data)) {
                $batches = [$data];
            }
        } catch (JRPCException $e) {
            return $this->json(data: new ErrorResponse(error: $e, id: $data['id'] ?? null), headers: ['Access-Control-Allow-Origin' => implode(', ', $this->accessControlAllowOriginList)]);
        } catch (Throwable $e) {
            return $this->json(data: new ErrorResponse(error: $e, id: $data['id'] ?? null), headers: ['Access-Control-Allow-Origin' => implode(', ', $this->accessControlAllowOriginList)]);
        }

        $responses = [];
        foreach ($batches as $batch) {
            $responses[] = $this->processBatch($container, $batch, $version, $methodType);
        }

        $responses = array_values(array_filter($responses, fn($item) => !is_null($item)));

        if (count($responses) === 1 && $responses[0] instanceof PlainResponseInterface) {
            return $responses[0];
        }

        if (count($responses) > 1) {
            return $this->json(data: $responses, headers: ['Access-Control-Allow-Origin' => implode(', ', $this->accessControlAllowOriginList)]);
        } elseif(!empty($responses)) {
            return $this->json(data: $responses[0], headers: ['Access-Control-Allow-Origin' => implode(', ', $this->accessControlAllowOriginList)]);
        }
        return new JsonResponse(headers: ['Access-Control-Allow-Origin' => implode(', ', $this->accessControlAllowOriginList)]);
    }

    private function processBatch(
        Container $container,
        array $batch,
        string $version,
        string $methodType,
    ): ErrorResponse|BaseResponse|JsonResponse|PlainResponseInterface|null {
        try {
            $baseRequest = new BaseRequest($batch);

            $method = $this->specCollection->getMethodSpec($version, $baseRequest->getMethod());

            $allowed = false;
            if (!empty($method->getRoles())) {
                foreach ($method->getRoles() as $role) {
                    if ($this->security->isGranted($role)) {
                        $allowed = true;
                    }
                }
            }

            if (!$allowed && !empty($method->getRoles())) {
                return $this->json(data: 'Access not allowed', status: 403, headers: ['Access-Control-Allow-Origin' => implode(', ', $this->accessControlAllowOriginList)]);
            }

            if ($method->getRequestType() !== $methodType) {
                throw new JRPCException('Invalid Request.', JRPCException::INVALID_REQUEST);
            }

            $requestClass = $method->getRequest();
            if (!is_null($requestClass)) {
                $constructorParams = [];
                foreach ($method->getRequiredParameters() as $requiredParameter) {
                    if ($requiredParameter['name'] === 'id') {
                        $constructorParams[] = $baseRequest->getId();
                        continue;
                    }
                    $constructorParams[] = $baseRequest->getParams()[$requiredParameter['name']] ?? null;
                }

                $validators = [];
                foreach ($method->getValidators() as $field => $validatorItem) {
                    if ($validatorItem['allowsNull'] === false) {
                        $validators[$field] = new Assert\Type($validatorItem['type']);
                    } else {
                        $validators[$field] = new Assert\Optional([
                            new Assert\AtLeastOneOf([
                                new Assert\Type($validatorItem['type']),
                                new Assert\Blank(),
                                new Assert\IsNull(),
                            ])
                        ]);
                    }
                }

                $requestData = $baseRequest->getParams();
                if (!is_null($baseRequest->getId())) {
                    $requestData = $requestData + ['id' => $baseRequest->getId()];
                }

                $violations = $this->validator->validate(
                    $requestData,
                    new Assert\Collection($validators)
                );

                if ($violations->count()) {
                    $errs = [];

                    foreach ($violations as $violation) {
                        $errs[] = sprintf('%s - %s', $violation->getPropertyPath(), $violation->getMessage());
                    }

                    throw new JRPCException('Invalid params.', JRPCException::INVALID_PARAMS, implode(PHP_EOL, $errs));
                }

                $requestInstance = new $requestClass(...$constructorParams);

                foreach ($method->getAllParameters() as $allParameter) {
                    $requestSetter = $method->getRequestSetters()[$allParameter['name']] ?? null;
                    if (!is_null($requestSetter)) {
                        $value = $baseRequest->getParams()[$allParameter['name']] ?? null;
                        if ($allParameter['name'] === 'id') {
                            $value = $baseRequest->getParams()[$allParameter['name']] ?? $baseRequest->getId() ?? null;
                        }

                        if (is_null($value) && $allParameter['name'] === 'params') {
                            $value = $baseRequest->getParams();
                        }

                        $requestInstance->$requestSetter($value);
                    }
                }
            }

            $processorClass = $method->getMethodClass();
            $processor      = $container->get($processorClass);

            if ($method->isCallbacksExists()) {
                $callbacks = $processor->getCallbacks();

                if (!empty($callbacks)) {
                    foreach ($callbacks as $processorClassName => $callbackArr) {
                        if ($processorClassName !== $processorClass) {
                            continue;
                        }

                        foreach ($callbackArr as $func) {
                            $processor->$func($processorClass, $requestInstance ?? null);
                        }
                    }
                }
            }

            /** @var mixed|Response $result */
            $result = $processor->call($requestInstance ?? null);

            if ($method->isPlainResponse() && $result instanceof PlainResponseInterface) {
                $result->headers->add(['Access-Control-Allow-Origin' => implode(', ', $this->accessControlAllowOriginList)]);
                return $result;
            } else {
                if (!is_null($baseRequest->getId())) {
                    return new BaseResponse($result, $baseRequest?->getId() ?? null);
                } elseif (!empty((array)$result)) {
                    return new BaseResponse($result);
                }
                unset($baseRequest);
            }
        } catch (JRPCException $e) {
            return new ErrorResponse(error: $e, id: $baseRequest?->getId() ?? $batch['id'] ?? null);
        } catch (Throwable $e) {
            return new ErrorResponse(error: $e, id: $baseRequest?->getId() ?? $batch['id'] ?? null);
        }

        return null;
    }

    private function isBatch(array $data): bool
    {
        if (
            isset($data[0])
            && isset($data[1])
            && is_array($data[0])
            && is_array($data[1])
            && array_key_exists('jsonrpc', $data[0])
            && array_key_exists('jsonrpc', $data[1])
        ) {
            return true;
        }

        return false;
    }
}