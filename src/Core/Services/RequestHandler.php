<?php

namespace OV\JsonRPCAPIBundle\Core\Services;

use OV\JsonRPCAPIBundle\Core\PreProcessorInterface;
use OV\JsonRPCAPIBundle\Core\Request\BaseRequest;
use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Response\BaseResponse;
use OV\JsonRPCAPIBundle\Core\Response\ErrorResponse;
use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use OV\JsonRPCAPIBundle\Core\Response\OvResponseInterface;
use OV\JsonRPCAPIBundle\Core\Response\PlainResponseInterface;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler\HandleBatchInterface;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;

final readonly class RequestHandler
{
    public function __construct(
        private Security $security,
        private MethodSpecCollection $specCollection,
        private ValidatorInterface $validator,
        private HeadersPreparer $headersPreparer,
        private Container $container,
        private ResponseService $responseService,
    ) {
    }

    public function applyStrategy(HandleBatchInterface $strategy, array $data, int $version, string $methodType): ?OvResponseInterface
    {
        return $strategy->handleBatch($data, $version, $methodType, [$this, 'processBatch']);
    }

    public function processBatch(
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

            $res = $this->checkRoles($methodSpec);
            if (!is_null($res)) {
                return $res;
            }

            $requestClass = $methodSpec->getRequest();
            $requestInstance = null;
            if (!is_null($requestClass)) {
                $requestInstance = $this->processRequestClass($methodSpec, $baseRequest, $requestClass);
            }

            $this->processValidatorsForRequestInstance($methodSpec, $baseRequest);

            $processorClass = $methodSpec->getMethodClass();
            $processor = $this->container->get($processorClass);

            if ($methodSpec->isPreProcessorExists() && $processor instanceof PreProcessorInterface) {
                $this->runPreProcessors($processor, $processorClass, $requestInstance);
            }

            /** @var mixed|Response $result */
            $result = $processor->call($requestInstance);

            if ($methodSpec->isPlainResponse() && $result instanceof PlainResponseInterface) {
                $result->headers->add($this->headersPreparer->prepareHeaders());

                return $result;
            }

            if (!is_null($baseRequest->getId()) || !empty((array)$result)) {
                return $this->responseService->prepareJsonResponse(new BaseResponse($result, $baseRequest->getId() ?? null));
            }
            unset($baseRequest);
        } catch (JRPCException|Throwable $e) {
            match (true) {
                isset($baseRequest) => $id = $baseRequest->getId(),
                isset($batch['id']) => $id = $batch['id'],
                default => $id = null,
            };

            return $this->responseService->prepareJsonResponse(new ErrorResponse(error: $e, id: $id));
        }

        return null;
    }

    private function runPreProcessors(
        PreProcessorInterface $processor,
        string $processorClass,
        ?object $requestInstance = null
    ): void {
        $preProcessors = $processor->getPreProcessors();

        if (!empty($preProcessors)) {
            foreach ($preProcessors as $processorClassName => $preProcessorsArr) {
                if ($processorClassName !== $processorClass) {
                    continue;
                }

                foreach ($preProcessorsArr as $func) {
                    $processor->$func($processorClass, $requestInstance);
                }
            }
        }
    }

    private function processRequestClass(MethodSpec $methodSpec, BaseRequest $baseRequest, string $requestClass): mixed
    {
        $constructorParams = [];
        foreach ($methodSpec->getRequiredParameters() as $requiredParameter) {
            if ($requiredParameter['name'] === 'id') {
                $constructorParams[] = $baseRequest->getId();
                continue;
            }
            $constructorParams[] = $baseRequest->getParams()[$requiredParameter['name']] ?? null;
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

        return $requestInstance;
    }

    /**
     * @throws JRPCException
     */
    private function processValidatorsForRequestInstance(MethodSpec $methodSpec, BaseRequest $baseRequest): void
    {
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
    }

    private function checkRoles(MethodSpec $methodSpec): ?JsonResponse
    {
        if (!empty($methodSpec->getRoles())) {
            $allowed = false;

            foreach ($methodSpec->getRoles() as $role) {
                if ($this->security->isGranted($role)) {
                    $allowed = true;
                }
            }

            if (!$allowed) {
                return new JsonResponse(data: 'Access not allowed', status: 403, headers: $this->headersPreparer->prepareHeaders());
            }
        }

        return null;
    }
}