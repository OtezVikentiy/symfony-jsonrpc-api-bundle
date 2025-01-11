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
use OV\JsonRPCAPIBundle\Core\CallbacksInterface;
use OV\JsonRPCAPIBundle\Core\Response\PlainResponseInterface;
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
    private const string CALL_METHOD = 'call';

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
            MethodSpecCollection::class
        );

        $methods = $container->findTaggedServiceIds('ov.rpc.method');

        foreach ($methods as $method => $tags) {
            $methodDefinition = $container->findDefinition($method);
            $className        = $methodDefinition->getClass();

            $methodDefinition->setPublic(true);
            $methodDefinition->setAutowired(true);
            $methodDefinition->setAutoconfigured(true);

            $methodReflectionClass = new ReflectionClass($className);

            $methodName = $requestType = null;
            $attributes = $methodReflectionClass->getAttributes(JsonRPCAPI::class);
            $roles = [];
            $apiTags = [];
            $summary = '';
            $description = '';
            $ignoreInSwagger = false;
            $version = null;
            foreach ($attributes as $attribute) {
                if ($attribute->getName() === JsonRPCAPI::class) {
                    $methodName = $attribute->getArguments()['methodName'] ?? throw new Exception(sprintf('Class %s does not have attribute param methodName', $className));
                    $requestType = $attribute->getArguments()['type'] ?? throw new Exception(sprintf('Class %s does not have attribute param type', $className));
                    $summary = $attribute->getArguments()['summary'] ?? '';
                    $description = $attribute->getArguments()['description'] ?? '';
                    $ignoreInSwagger = $attribute->getArguments()['ignoreInSwagger'] ?? false;
                    $roles = $attribute->getArguments()['roles'] ?? [];
                    $apiTags = $attribute->getArguments()['tags'] ?? [];
                    $version = $attribute->getArguments()['version'] ?? null;
                }
            }

            if (is_null($version)) {
                $namespace = $methodReflectionClass->getNamespaceName();
                preg_match('/V[0-9]+$/', $namespace, $matches);
                if (empty($matches)) {
                    throw new RuntimeException(
                        sprintf(
                            'Version for API endpoint %s is not defined. Either use the version parameter in the 
                            JsonRPCAPI attribute explicitly, or specify the API version number in the namespace, 
                            for example App\\RPC\\V1',
                            $namespace . '\\' . $className,
                        )
                    );
                }

                $version = (int)preg_replace('/[A-Za-z]+/', '', $matches[0]);

                if (empty($version) || $version == 0) {
                    throw new RuntimeException(
                        sprintf(
                            'Version for API endpoint %s is not defined or zero. Either use the version parameter in the 
                            JsonRPCAPI attribute explicitly, or specify the API version number in the namespace, 
                            for example App\\RPC\\V1',
                            $namespace . '\\' . $className,
                        )
                    );
                }
            }

            if (is_null($methodName) || is_null($requestType)) {
                continue;
            }

            if (!$methodReflectionClass->hasMethod(self::CALL_METHOD)) {
                throw new RuntimeException(
                    sprintf(
                        'Method %s::%s is not defined',
                        $className,
                        self::CALL_METHOD
                    )
                );
            }

            $allParameters           = [];
            $requiredParameters      = [];
            $requestSetters          = [];
            $validators              = [];
            $methodRequestReflection = null;
            $callParameters          = $methodReflectionClass->getMethod('call')->getParameters();

            if (count($callParameters) > 1) {
                throw new RuntimeException(
                    sprintf(
                        'Method %s::%s should have one or zero incoming parameters',
                        $className,
                        self::CALL_METHOD
                    )
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
                    if (str_starts_with($requestSingleMethod->getName(), 'set')) {
                        $name = $requestSingleMethod->getParameters()[0]?->getName() ?? null;
                        if (!is_null($name)) {
                            $requestSetters[$name] = $requestSingleMethod->getName();
                        }
                    }
                }
            }

            $plainResponse = false;
            $callResponseType = $methodReflectionClass->getMethod('call')->getReturnType();
            if ($callResponseType instanceof ReflectionUnionType) {
                $callResponseTypes = $callResponseType->getTypes();
                foreach ($callResponseTypes as $callResponseType) {
                    $responseTypeReflection = new ReflectionClass($callResponseType->getName());
                    $interfaces = $responseTypeReflection->getInterfaces();
                    foreach ($interfaces as $interface) {
                        if ($interface->getName() === PlainResponseInterface::class) {
                            $plainResponse = true;
                        }
                    }
                }
            }

            $callbacksExists = false;
            $parentClass = $methodReflectionClass->getParentClass();
            if ($parentClass) {
                $callbacksExists = $parentClass->implementsInterface(CallbacksInterface::class);
            }

            $methodAlias            = $this->getMethodAlias($methodName, $methodReflectionClass->getNamespaceName() . '\\' ?? '');
            $methodSpecDefinitionId = uniqid('OV_JSON_RPC_API_' . $methodAlias, true);
            $methodSpec             = $container->register($methodSpecDefinitionId, MethodSpec::class);

            $methodSpec->setArguments([
                $methodReflectionClass->getName(),
                $requestType,
                $summary,
                $description,
                $ignoreInSwagger,
                $methodName,
                $allParameters,
                $requiredParameters,
                $methodRequestReflection?->getName() ?? null,
                $requestSetters,
                $validators,
                $roles,
                $apiTags,
                $plainResponse,
                $callbacksExists,
            ])->setPublic(true)->setAutowired(true)->setAutoconfigured(true)->setLazy(true);

            $methodSpecCollectionDefinition->addMethodCall(
                'addMethodSpec',
                [
                    '$version' => $version,
                    '$methodName' => $methodName,
                    '$methodSpec' => new Reference($methodSpecDefinitionId),
                ]
            );
        }
    }

    private function getProperties(array $properties = []): array
    {
        $return = [];
        foreach ($properties as $property) {
            $return[] = [
                'name' => $property->getName(),
                'type' => $property->getType()->getName(),
            ];
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
                'allowsNull' => $property->getType()->allowsNull(),
            ];
        }

        $methodsIdx = [];
        foreach ($methods as $method) {
            $methodsIdx[$method->getName()] = $method;
        }

        foreach ($propertiesIdx as $name => $typeData) {
            $getter                         = $methodsIdx['get' . ucfirst($name)];
            $setter                         = $methodsIdx['set' . ucfirst($name)];
            $setterAndPropertyTypesAreEqual = $setter->getParameters()[0]->getType()->getName() !== $typeData['type'];
            if ($setterAndPropertyTypesAreEqual) {
                throw new Exception(
                    sprintf(
                        'Property %s of method %s has invalid data type in setter %s',
                        $name,
                        $requestReflection->getName(),
                        $setter->getName()
                    )
                );
            }
            $getterAndPropertyTypesAreEqual = $getter->getReturnType()->getName() !== $typeData['type'];
            if ($getterAndPropertyTypesAreEqual) {
                throw new Exception(
                    sprintf(
                        'Property %s of method %s has invalid data type in getter %s',
                        $name,
                        $requestReflection->getName(),
                        $getter->getName()
                    )
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