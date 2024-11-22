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
use OV\JsonRPCAPIBundle\Core\PlainResponseInterface;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class CompilerPass implements CompilerPassInterface
{
    private const CALL_METHOD = 'call';

    public function __construct(
        private readonly NameConverterInterface $nameConverter,
    ) {
    }

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
            foreach ($attributes as $attribute) {
                if ($attribute->getName() === JsonRPCAPI::class) {
                    $methodName = $attribute->getArguments()['methodName'] ?? throw new Exception(sprintf('Class %s does not have attribute param methdoName', $className));
                    $requestType = $attribute->getArguments()['type'] ?? throw new Exception(sprintf('Class %s does not have attribute param type', $className));
                    $summary = $attribute->getArguments()['summary'] ?? '';
                    $description = $attribute->getArguments()['description'] ?? '';
                    $ignoreInSwagger = $attribute->getArguments()['ignoreInSwagger'] ?? false;
                    $roles = $attribute->getArguments()['roles'] ?? [];
                }
            }

            if (is_null($methodName) || is_null($requestType)) {
                continue;
            }

            if (!$methodReflectionClass->hasMethod(self::CALL_METHOD)) {
                throw new \RuntimeException(
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
            foreach ($callParameters as $callParameter) {
                if ($callParameter->getName() === 'request') { //TODO тут получается так, что на входе можно использовать только переменную request
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
            }

            $plainResponse = false;
            $callResponseType = $methodReflectionClass->getMethod('call')->getReturnType();
            if ($callResponseType instanceof \ReflectionUnionType) {
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

            $methodAlias            = $this->getMethodAlias($methodName, $tags[0]['namespace'] ?? '');
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
                $plainResponse,
                $callbacksExists,
            ])->setPublic(true)->setAutowired(true)->setAutoconfigured(true);

            $methodSpecCollectionDefinition->addMethodCall(
                'addMethodSpec',
                [
                    '$version' => $tags[0]['version'] ?? 1,
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