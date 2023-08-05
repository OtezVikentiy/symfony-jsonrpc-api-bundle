<?php

namespace OV\JsonRPCAPIBundle\DependencyInjection;

use Doctrine\Common\Annotations\AnnotationReader;
use Exception;
use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class JsonRPCAPICompilerPass implements CompilerPassInterface
{
    private const CALL_METHOD = 'call';

    /**
     * @param AnnotationReader $annotationReader
     */
    public function __construct(
        private readonly AnnotationReader $annotationReader
    ) {
    }

    /**
     * @param ContainerBuilder $container
     * @return void
     * @throws ReflectionException
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
            $className = $methodDefinition->getClass();

            $methodDefinition->setPublic(true);
            $methodDefinition->setAutowired(true);

            $methodReflectionClass = new ReflectionClass($className);

            $classAnnotation = $this->annotationReader->getClassAnnotation($methodReflectionClass, JsonRPCAPI::class);
            if (!$classAnnotation) {
                continue;
            }

            $methodName = $classAnnotation->getMethodName();

            if (!$methodReflectionClass->hasMethod(self::CALL_METHOD)) {
                throw new \RuntimeException(sprintf(
                    'Method %s::%s is not defined',
                    $className,
                    self::CALL_METHOD
                ));
            }

            $allParameters = [];
            $requiredParameters = [];
            $requestSetters = [];
            $methodRequestReflection = null;
            $callParameters = $methodReflectionClass->getMethod('call')->getParameters();
            foreach ($callParameters as $callParameter) {
                if ($callParameter->getName() === 'request') {
                    $methodRequestReflection = new ReflectionClass($callParameter->getType()->getName());
                    $allParameters = array_map(fn($i) => $i->getName(), $methodRequestReflection->getProperties());
                    $requiredParameters = array_map(fn($i) => $i->getName(), $methodRequestReflection->getConstructor()->getParameters());
                    $requestMethods = $methodRequestReflection->getMethods();
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

            $methodSpecCollectionDefinition
                ->addMethodCall(
                    'addMethodSpec',
                    [
                        '$methodName' => $methodName,
                        '$methodSpec' => new Definition(
                            MethodSpec::class,
                            [
                                '$methodClass' => $methodReflectionClass->getName(),
                                '$allParameters' => $allParameters,
                                '$requiredParameters' => $requiredParameters,
                                '$request' => $methodRequestReflection?->getName() ?? null,
                                '$requestSetters' => $requestSetters,
                            ]
                        )
                    ]
                );
        }
    }
}