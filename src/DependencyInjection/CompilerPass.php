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

use Doctrine\Common\Annotations\AnnotationReader;
use Exception;
use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class CompilerPass implements CompilerPassInterface
{
    private const CALL_METHOD = 'call';

    /**
     * @param AnnotationReader       $annotationReader
     * @param NameConverterInterface $nameConverter
     */
    public function __construct(
        private readonly AnnotationReader $annotationReader,
        private readonly NameConverterInterface $nameConverter,
    ) {
    }

    /**
     * @param ContainerBuilder $container
     *
     * @return void
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

            $methodReflectionClass = new ReflectionClass($className);

            $classAnnotation = $this->annotationReader->getClassAnnotation($methodReflectionClass, JsonRPCAPI::class);
            if (!$classAnnotation) {
                continue;
            }

            $methodName = $classAnnotation->getMethodName();

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
                if ($callParameter->getName() === 'request') {
                    $methodRequestReflection = new ReflectionClass($callParameter->getType()->getName());
                    $validators              = $this->getValidatorsForRequest($methodRequestReflection);
                    $allParameters           = array_map(fn($i) => $i->getName(), $methodRequestReflection->getProperties());
                    $requiredParameters      = array_map(fn($i) => $i->getName(), $methodRequestReflection->getConstructor()->getParameters());
                    $requestMethods          = $methodRequestReflection->getMethods();

                    foreach ($requestMethods as $requestSingleMethod) {
                        if (str_contains($requestSingleMethod->getName(), 'set')) {
                            $name = $requestSingleMethod->getParameters()[0]?->getName() ?? null;
                            if (!is_null($name)) {
                                $requestSetters[$name] = $requestSingleMethod->getName();
                            }
                        }
                    }
                }
            }

            $methodAlias            = $this->getMethodAlias($methodName, $tag['namespace'] ?? '');
            $methodSpecDefinitionId = uniqid('OV_JSON_RPC_API_' . $methodAlias, true);
            $methodSpec             = $container->register($methodSpecDefinitionId, MethodSpec::class);

            $methodSpec->setArguments([
                $methodReflectionClass->getName(),
                $allParameters,
                $requiredParameters,
                $methodRequestReflection?->getName() ?? null,
                $requestSetters,
                $validators,
            ]);

            $methodSpecCollectionDefinition->addMethodCall(
                'addMethodSpec',
                [
                    '$methodName' => $methodName,
                    '$methodSpec' => new Reference($methodSpecDefinitionId),
                ]
            );
        }
    }

    /**
     * @param ReflectionClass $requestReflection
     *
     * @return array
     * @throws Exception
     */
    private function getValidatorsForRequest(ReflectionClass $requestReflection): array
    {
        $validatorsIdx = [];

        $methods    = $requestReflection->getMethods();
        $properties = $requestReflection->getProperties();

        $propertiesIdx = [];
        foreach ($properties as $property) {
            $propertiesIdx[$property->getName()] = $property->getType()->getName();
        }

        $methodsIdx = [];
        foreach ($methods as $method) {
            $methodsIdx[$method->getName()] = $method;
        }

        foreach ($propertiesIdx as $name => $type) {
            $getter                         = $methodsIdx['get' . ucfirst($name)];
            $setter                         = $methodsIdx['set' . ucfirst($name)];
            $setterAndPropertyTypesAreEqual = $setter->getParameters()[0]->getType()->getName() !== $type;
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
            $getterAndPropertyTypesAreEqual = $getter->getReturnType()->getName() !== $type;
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

            $validatorsIdx[$name] = $type;
        }

        return $validatorsIdx;
    }

    /**
     * @param string $methodClass
     * @param string $namespace
     *
     * @return string
     */
    private function getMethodAlias(string $methodClass, string $namespace): string
    {
        $methodParts = explode('\\', ltrim(str_replace($namespace, '', $methodClass), '\\'));

        return implode('.', array_map([$this->nameConverter, 'normalize'], $methodParts));
    }
}