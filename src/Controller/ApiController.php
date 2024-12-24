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


use OV\JsonRPCAPIBundle\Core\Request\BaseRequest;
use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Response\BaseResponse;
use OV\JsonRPCAPIBundle\Core\Response\ErrorResponse;
use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;
use OV\JsonRPCAPIBundle\Core\Response\PlainResponseInterface;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;

final class ApiController extends BaseController
{
    public function __construct(
        private readonly array $accessControlAllowOriginList,
        private readonly Security $security,
        private readonly MethodSpecCollection $specCollection,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/v{version<\d+>}', name: 'ov_json_rpc_api_index', methods: ['POST', 'GET', 'PUT', 'PATCH', 'DELETE'])]
    public function index(Request $request, Container $container): OvResponseInterface
    {
        try {
            $data = $this->prepareData($request);

            if (empty($data)) {
                throw new JRPCException('Invalid Request.', JRPCException::INVALID_REQUEST);
            }

            $batches = $data;
            if (!$this->isBatch($data)) {
                $batches = [$data];
            }
        } catch (JRPCException|Throwable $e) {
            return $this->json(
                data: new ErrorResponse(error: $e, id: $data['id'] ?? null),
                headers: $this->prepareHeaders()
            );
        }

        $responses = [];
        foreach ($batches as $batch) {
            $responses[] = $this->processBatch($container, $batch, $this->getVersion($request), $request->getMethod());
        }

        $responses = array_values(array_filter($responses, fn($item) => !is_null($item)));

        if (empty($responses)) {
            return new JsonResponse(headers: $this->prepareHeaders());
        }

        if (count($responses) === 1 && $responses[0] instanceof PlainResponseInterface) {
            return $responses[0];
        }

        $data = (count($responses) > 1) ? $responses : $responses[0];

        return $this->json(data: $data, headers: $this->prepareHeaders());
    }

    private function processBatch(
        Container $container,
        array $batch,
        int $version,
        string $methodType,
    ): ?OvResponseInterface {
        try {
            $baseRequest = new BaseRequest($batch);

            $methodSpec = $this->specCollection->getMethodSpec($version, $baseRequest->getMethod());

            if ($methodSpec->getRequestType() !== $methodType) {
                throw new JRPCException('Invalid Request.', JRPCException::INVALID_REQUEST);
            }

            $allowed = false;
            if (!empty($methodSpec->getRoles())) {
                foreach ($methodSpec->getRoles() as $role) {
                    if ($this->security->isGranted($role)) {
                        $allowed = true;
                    }
                }
            }

            if (!$allowed && !empty($methodSpec->getRoles())) {
                return $this->json(data: 'Access not allowed', status: 403, headers: $this->prepareHeaders());
            }

            $requestClass = $methodSpec->getRequest();
            if (!is_null($requestClass)) {
                $constructorParams = [];
                foreach ($methodSpec->getRequiredParameters() as $requiredParameter) {
                    if ($requiredParameter['name'] === 'id') {
                        $constructorParams[] = $baseRequest->getId();
                        continue;
                    }
                    $constructorParams[] = $baseRequest->getParams()[$requiredParameter['name']] ?? null;
                }

                $validators = [];
                foreach ($methodSpec->getValidators() as $field => $validatorItem) {
                    if ($validatorItem['allowsNull'] === false) {
                        $validators[$field] = new Assert\Type($validatorItem['type']);
                    } else {
                        $validators[$field] = new Assert\Optional([
                            new Assert\AtLeastOneOf([
                                new Assert\Type($validatorItem['type']),
                                new Assert\Blank(),
                                new Assert\IsNull(),
                            ]),
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

                foreach ($methodSpec->getAllParameters() as $allParameter) {
                    $requestSetter = $methodSpec->getRequestSetters()[$allParameter['name']] ?? null;
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

            $processorClass = $methodSpec->getMethodClass();
            $processor = $container->get($processorClass);

            if ($methodSpec->isCallbacksExists()) {
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

            if ($methodSpec->isPlainResponse() && $result instanceof PlainResponseInterface) {
                $result->headers->add($this->prepareHeaders());

                return $result;
            } else {
                if (!is_null($baseRequest->getId())) {
                    return new BaseResponse($result, $baseRequest?->getId() ?? null);
                } elseif (!empty((array)$result)) {
                    return new BaseResponse($result);
                }
                unset($baseRequest);
            }
        } catch (JRPCException|Throwable $e) {
            match (true) {
                isset($baseRequest) => $id = $baseRequest->getId(),
                isset($batch['id']) => $id = $batch['id'],
                default => $id = null,
            };

            return new ErrorResponse(error: $e, id: $id);
        }

        return null;
    }

    private function getVersion(Request $request): int
    {
        $pathArray = explode('/', $request->getPathInfo());

        return (int)preg_replace('/\D+/', '', $pathArray[count($pathArray) - 1]);
    }

    private function prepareData(Request $request): array
    {
        $data = [];

        if ($request->getMethod() === 'GET') {
            $data = $request->query->all();
        } elseif (in_array($request->getMethod(), ['POST', 'DELETE', 'PUT', 'PATCH'])) {
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

        return $data;
    }

    private function prepareHeaders(): array
    {
        return ['Access-Control-Allow-Origin' => implode(', ', $this->accessControlAllowOriginList)];
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