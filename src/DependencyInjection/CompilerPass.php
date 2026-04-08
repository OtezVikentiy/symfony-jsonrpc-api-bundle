<?php
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\DependencyInjection;

use Exception;
use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\Core\PostProcessorInterface;
use OV\JsonRPCAPIBundle\Core\PreProcessorInterface;
use OV\JsonRPCAPIBundle\Core\Response\PlainResponseInterface;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use ReflectionClass;
use ReflectionException;
use ReflectionUnionType;
use RuntimeException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

final class CompilerPass implements CompilerPassInterface
{
    private const CALL_METHOD = 'call';

    public function __construct(
        private readonly NameConverterInterface $nameConverter,
    ) {
    }

    /**
     * @noinspection PhpUnused
     * @throws ReflectionException
     * @throws Exception
     */
    public function process(ContainerBuilder $container): void
    {
        $methodSpecCollectionDefinition = $container->autowire(
            MethodSpecCollection::class,
            MethodSpecCollection::class,
        );

        $methods = $container->findTaggedServiceIds('ov.rpc.method');

        foreach ($methods as $method => $tags) {
            $methodDefinition = $container->findDefinition($method);
            $className        = $methodDefinition->getClass();

            $methodDefinition->setPublic(true);
            $methodDefinition->setAutowired(true);
            $methodDefinition->setAutoconfigured(true);

            $methodReflectionClass = new ReflectionClass($className);

            $metadata = $this->extractAttributeMetadata($methodReflectionClass, $className);
            if ($metadata === null) {
                continue;
            }

            $version = $this->resolveVersion($methodReflectionClass, $metadata['version'], $className);

            if (!$methodReflectionClass->hasMethod(self::CALL_METHOD)) {
                throw new RuntimeException(
                    sprintf(
                        'Method %s::%s is not defined',
                        $className,
                        self::CALL_METHOD,
                    ),
                );
            }

            $requestAnalysis = $this->analyzeRequestClass($methodReflectionClass, $className);
            $plainResponse = $this->detectPlainResponse($methodReflectionClass);
            [$preProcessorExists, $postProcessorExists] = $this->detectProcessors($methodReflectionClass);

            $methodAlias            = $this->getMethodAlias($metadata['methodName'], $methodReflectionClass->getNamespaceName() . '\\' ?? '');
            $methodSpecDefinitionId = uniqid('OV_JSON_RPC_API_' . $methodAlias, true);
            $methodSpec             = $container->register($methodSpecDefinitionId, MethodSpec::class);

            $methodSpec->setArguments([
                $methodReflectionClass->getName(),
                $metadata['requestType'],
                $metadata['methodName'],
                new RequestMetadata(
                    $requestAnalysis['requestClass'],
                    $requestAnalysis['allParameters'],
                    $requestAnalysis['requiredParameters'],
                    $requestAnalysis['requestGetters'],
                    $requestAnalysis['requestSetters'],
                    $requestAnalysis['requestAdders'],
                    $requestAnalysis['validators'],
                ),
                new SwaggerMetadata(
                    $metadata['summary'],
                    $metadata['description'],
                    $metadata['ignoreInSwagger'],
                    $metadata['apiTags'],
                    $metadata['group'],
                ),
                $metadata['roles'],
                $plainResponse,
                $preProcessorExists,
                $postProcessorExists,
            ])->setPublic(true)->setAutowired(true)->setAutoconfigured(true);

            if (PHP_VERSION_ID >= 80300) {
                $methodSpec->setLazy(true);
            }

            $methodSpecCollectionDefinition->addMethodCall(
                'addMethodSpec',
                [
                    '$version' => $version,
                    '$methodName' => $metadata['methodName'],
                    '$methodSpec' => new Reference($methodSpecDefinitionId),
                ],
            );
        }
    }

    private function extractAttributeMetadata(ReflectionClass $reflectionClass, string $className): ?array
    {
        $attributes = $reflectionClass->getAttributes(JsonRPCAPI::class);

        foreach ($attributes as $attribute) {
            if ($attribute->getName() === JsonRPCAPI::class) {
                return [
                    'methodName' => $attribute->getArguments()['methodName'] ?? throw new Exception(sprintf('Class %s does not have attribute param methodName', $className)),
                    'requestType' => $attribute->getArguments()['type'] ?? throw new Exception(sprintf('Class %s does not have attribute param type', $className)),
                    'summary' => $attribute->getArguments()['summary'] ?? '',
                    'description' => $attribute->getArguments()['description'] ?? '',
                    'ignoreInSwagger' => $attribute->getArguments()['ignoreInSwagger'] ?? false,
                    'roles' => $attribute->getArguments()['roles'] ?? [],
                    'apiTags' => $attribute->getArguments()['tags'] ?? [],
                    'version' => $attribute->getArguments()['version'] ?? null,
                    'group' => $attribute->getArguments()['group'] ?? null,
                ];
            }
        }

        return null;
    }

    private function resolveVersion(ReflectionClass $reflectionClass, ?int $attributeVersion, string $className): int
    {
        if ($attributeVersion !== null) {
            return $attributeVersion;
        }

        $namespace = $reflectionClass->getNamespaceName();
        if (preg_match('/\\\\(V[0-9]+)(?:\\\\|$)/', $namespace, $matches) === 0) {
            throw new RuntimeException(
                sprintf(
                    'Version for API endpoint %s is not defined. Either use the version parameter in the
                    JsonRPCAPI attribute explicitly, or specify the API version number in the namespace,
                    for example App\\RPC\\V1',
                    $namespace . '\\' . $className,
                ),
            );
        }

        $version = (int)preg_replace('/[A-Za-z]+/', '', $matches[1]);

        if (empty($version) || $version == 0) {
            throw new RuntimeException(
                sprintf(
                    'Version for API endpoint %s is not defined or zero. Either use the version parameter in the
                    JsonRPCAPI attribute explicitly, or specify the API version number in the namespace,
                    for example App\\RPC\\V1',
                    $namespace . '\\' . $className,
                ),
            );
        }

        return $version;
    }

    /**
     * @throws Exception
     */
    private function analyzeRequestClass(ReflectionClass $methodReflectionClass, string $className): array
    {
        $allParameters           = [];
        $requiredParameters      = [];
        $requestGetters          = [];
        $requestSetters          = [];
        $requestAdders           = [];
        $validators              = [];
        $methodRequestReflection = null;
        $callParameters          = $methodReflectionClass->getMethod('call')->getParameters();

        if (count($callParameters) > 1) {
            throw new RuntimeException(
                sprintf(
                    'Method %s::%s should have one or zero incoming parameters',
                    $className,
                    self::CALL_METHOD,
                ),
            );
        }

        if (!empty($callParameters[0])) {
            $callParameter = $callParameters[0];
            $methodRequestReflection = new ReflectionClass($callParameter->getType()->getName());
            $validators              = $this->getValidatorsForRequest($methodRequestReflection);
            $allParameters           = $this->getProperties($methodRequestReflection->getProperties() ?? []);
            $requiredParameters      = $this->getProperties($methodRequestReflection->getConstructor()?->getParameters() ?? []);
            $requestMethods          = $methodRequestReflection->getMethods();

            foreach ($requestMethods as $requestSingleMethod) {
                $name = $requestSingleMethod->getName();
                if (str_starts_with($name, 'set')) {
                    $name = $requestSingleMethod->getParameters()[0]?->getName() ?? null;
                    if (!is_null($name)) {
                        $requestSetters[$name] = $requestSingleMethod->getName();
                    }
                } elseif (str_starts_with($name, 'add')) {
                    $name = $requestSingleMethod->getParameters()[0]?->getName() ?? null;
                    if (!is_null($name)) {
                        $requestAdders[$name] = $requestSingleMethod->getName();

                        unset($requestSetters[$name]);

                        foreach ($allParameters as $k => $allParameter) {
                            if (str_contains($allParameter['name'], $name)) {
                                $allParameters[$k]['type'] = $requestSingleMethod->getParameters()[0]?->getType()?->getName();
                            }
                        }
                    }
                } elseif (str_starts_with($name, 'get') || str_starts_with($name, 'is')) {
                    foreach ($allParameters as $k => $allParameter) {
                        if (str_contains(mb_strtolower($name), mb_strtolower($allParameter['name']))) {
                            $requestGetters[$allParameter['name']] = $name;
                        }
                    }
                }
            }
        }

        return [
            'allParameters' => $allParameters,
            'requiredParameters' => $requiredParameters,
            'requestGetters' => $requestGetters,
            'requestSetters' => $requestSetters,
            'requestAdders' => $requestAdders,
            'validators' => $validators,
            'requestClass' => $methodRequestReflection?->getName() ?? null,
        ];
    }

    private function detectPlainResponse(ReflectionClass $methodReflectionClass): bool
    {
        $callResponseType = $methodReflectionClass->getMethod('call')->getReturnType();
        if ($callResponseType instanceof ReflectionUnionType) {
            foreach ($callResponseType->getTypes() as $type) {
                $responseTypeReflection = new ReflectionClass($type->getName());
                foreach ($responseTypeReflection->getInterfaces() as $interface) {
                    if ($interface->getName() === PlainResponseInterface::class) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @return array{bool, bool}
     */
    private function detectProcessors(ReflectionClass $methodReflectionClass): array
    {
        $preProcessorExists = false;
        $postProcessorExists = false;

        $parentClass = $methodReflectionClass->getParentClass();
        if ($parentClass) {
            $preProcessorExists = $parentClass->implementsInterface(PreProcessorInterface::class);
            $postProcessorExists = $parentClass->implementsInterface(PostProcessorInterface::class);
        }
        if ($methodReflectionClass->implementsInterface(PreProcessorInterface::class)) {
            $preProcessorExists = true;
        }
        if ($methodReflectionClass->implementsInterface(PostProcessorInterface::class)) {
            $postProcessorExists = true;
        }

        return [$preProcessorExists, $postProcessorExists];
    }

    private function getProperties(array $properties = []): array
    {
        $return = [];

        foreach ($properties as $property) {
            $propData = [
                'name' => $property->getName(),
                'type' => $property->getType()->getName(),
            ];

            if ($property instanceof \ReflectionProperty) {
                $method = 'hasDefaultValue';
            } elseif ($property instanceof \ReflectionParameter) {
                $method = 'isDefaultValueAvailable';
            } else {
                $return[] = $propData;
                continue;
            }

            if ($property->$method()) {
                $propData['defaultValue'] = $property->getDefaultValue();
            }

            $return[] = $propData;
        }
        return $return;
    }

    /**
     * @throws Exception
     */
    private function getValidatorsForRequest(ReflectionClass $requestReflection): array
    {
        $validatorsIdx = [];

        $methods    = $requestReflection->getMethods();
        $properties = $requestReflection->getProperties();

        $propertiesIdx = [];
        foreach ($properties as $property) {
            $propertiesIdx[$property->getName()] = [
                'type' => $property->getType()->getName(),
                'allowsNull' => $property->getType()->allowsNull() || $property->hasDefaultValue(),
            ];
        }

        $methodsIdx = [];
        foreach ($methods as $method) {
            $methodsIdx[$method->getName()] = $method;
        }

        foreach ($propertiesIdx as $name => $typeData) {
            if ($typeData['type'] === 'bool' || $typeData['type'] === 'boolean') {
                $getter = $methodsIdx['is' . ucfirst($name)];
            } else {
                $getter = $methodsIdx['get' . ucfirst($name)];
            }
            $setter = $methodsIdx['set' . ucfirst($name)];
            $setterParamType = $setter->getParameters()[0]->getType();
            if ($setterParamType === null) {
                continue;
            }
            $setterTypeMismatch = $setterParamType->getName() !== $typeData['type'];
            if ($setterTypeMismatch) {
                throw new Exception(
                    sprintf(
                        'Property %s of method %s has invalid data type in setter %s',
                        $name,
                        $requestReflection->getName(),
                        $setter->getName(),
                    ),
                );
            }
            $getterTypeMismatch = $getter->getReturnType()->getName() !== $typeData['type'];
            if ($getterTypeMismatch) {
                throw new Exception(
                    sprintf(
                        'Property %s of method %s has invalid data type in getter %s',
                        $name,
                        $requestReflection->getName(),
                        $getter->getName(),
                    ),
                );
            }

            $validatorsIdx[$name] = $typeData;
        }

        return $validatorsIdx;
    }

    private function getMethodAlias(string $methodClass, string $namespace): string
    {
        $methodParts = explode('\\', ltrim(str_replace($namespace, '', $methodClass), '\\'));

        return implode('.', array_map([$this->nameConverter, 'normalize'], $methodParts));
    }
}
