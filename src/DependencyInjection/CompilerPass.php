<?php

declare(strict_types=1);
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
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;
use RuntimeException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\String\Inflector\EnglishInflector;
use Symfony\Component\String\Inflector\InflectorInterface;

/**
 * @internal
 */
final class CompilerPass implements CompilerPassInterface
{
    private const CALL_METHOD = 'call';
    private const GETTER_PREFIXES = ['get', 'is'];
    private const MULTIPART_MAX_FILE_BYTES_PARAMETER = 'ov_json_rpc_api.multipart.max_file_bytes';

    public function __construct(
        private readonly NameConverterInterface $nameConverter,
        private readonly InflectorInterface $inflector = new EnglishInflector(),
    ) {
    }

    /**
     * @noinspection PhpUnused
     *
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
        $processorReferences = [];

        foreach ($methods as $method => $tags) {
            $methodDefinition = $container->findDefinition($method);
            $className = $methodDefinition->getClass();

            if ($className === null || !class_exists($className)) {
                throw new RuntimeException(
                    sprintf('Service %s tagged ov.rpc.method has no resolvable class.', $method),
                );
            }

            $methodDefinition->setAutowired(true);
            $methodDefinition->setAutoconfigured(true);

            $processorReferences[$className] = new Reference($method);

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

            $methodAlias = $this->getMethodAlias($metadata['methodName'], $methodReflectionClass->getNamespaceName() . '\\');
            $serviceIdSuffix = sprintf('%d_%s', $version, $methodAlias);

            $requestMetadataId = 'OV_JSON_RPC_API_REQ_' . $serviceIdSuffix;
            $container->register($requestMetadataId, RequestMetadata::class)
                ->setArguments([
                    $requestAnalysis['requestClass'],
                    $requestAnalysis['allParameters'],
                    $requestAnalysis['requiredParameters'],
                    $requestAnalysis['requestGetters'],
                    $requestAnalysis['requestSetters'],
                    $requestAnalysis['requestAdders'],
                    $requestAnalysis['validators'],
                ])->setPublic(false);

            $swaggerMetadataId = 'OV_JSON_RPC_API_SWG_' . $serviceIdSuffix;
            $container->register($swaggerMetadataId, SwaggerMetadata::class)
                ->setArguments([
                    $metadata['summary'],
                    $metadata['description'],
                    $metadata['ignoreInSwagger'],
                    $metadata['apiTags'],
                    $metadata['group'],
                ])->setPublic(false);

            $methodSpecDefinitionId = 'OV_JSON_RPC_API_' . $serviceIdSuffix;
            $methodSpec = $container->register($methodSpecDefinitionId, MethodSpec::class);

            $methodSpec->setArguments([
                $methodReflectionClass->getName(),
                $metadata['requestType'],
                $metadata['methodName'],
                new Reference($requestMetadataId),
                new Reference($swaggerMetadataId),
                $metadata['roles'],
                $plainResponse,
                $preProcessorExists,
                $postProcessorExists,
                $metadata['allowExtraFields'],
                $metadata['acceptsMultipart'],
                $this->maxFileBytes($container),
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

        if ($container->hasDefinition(RequestHandler::class)) {
            $container->getDefinition(RequestHandler::class)->setArgument(
                '$processorLocator',
                ServiceLocatorTagPass::register($container, $processorReferences),
            );
        }
    }

    /**
     * Read once per build, and tolerant of the parameter being absent: a container assembled without
     * the extension - which is how much of this suite builds one - still has to compile.
     */
    private function maxFileBytes(ContainerBuilder $container): int|string|null
    {
        if (!$container->hasParameter(self::MULTIPART_MAX_FILE_BYTES_PARAMETER)) {
            return null;
        }

        $value = $container->getParameter(self::MULTIPART_MAX_FILE_BYTES_PARAMETER);

        return is_int($value) || is_string($value) ? $value : null;
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
                    'allowExtraFields' => $attribute->getArguments()['allowExtraFields'] ?? false,
                    'acceptsMultipart' => $attribute->getArguments()['acceptsMultipart'] ?? false,
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
        if (preg_match('/\\\\(V[0-9]+)(?:\\\\|$)/', $namespace, $matches) !== 1) {
            throw new RuntimeException(
                sprintf(
                    'Version for API endpoint %s is not defined. Either use the version parameter in the
                    JsonRPCAPI attribute explicitly, or specify the API version number in the namespace,
                    for example App\\RPC\\V1',
                    $namespace . '\\' . $className,
                ),
            );
        }

        $version = (int) preg_replace('/[A-Za-z]+/', '', $matches[1]);

        if (empty($version)) {
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
        $allParameters = [];
        $requiredParameters = [];
        $requestGetters = [];
        $requestSetters = [];
        $requestAdders = [];
        $validators = [];
        $methodRequestReflection = null;
        $callParameters = $methodReflectionClass->getMethod('call')->getParameters();

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
            $callParameterType = $callParameter->getType();
            if (!$callParameterType instanceof ReflectionNamedType || !class_exists($callParameterType->getName())) {
                throw new RuntimeException(
                    sprintf(
                        'Parameter of method %s::%s has an unsupported or missing type',
                        $className,
                        self::CALL_METHOD,
                    ),
                );
            }
            $methodRequestReflection = new ReflectionClass($callParameterType->getName());
            $validators = $this->getValidatorsForRequest($methodRequestReflection);
            $allParameters = $this->getProperties($methodRequestReflection->getProperties());
            $requiredParameters = $this->getProperties($methodRequestReflection->getConstructor()?->getParameters() ?? []);

            foreach ($allParameters as $index => $allParameter) {
                $propertyName = $allParameter['name'];

                $getter = $this->resolveGetter($methodRequestReflection, $propertyName);
                if ($getter !== null) {
                    $requestGetters[$propertyName] = $getter;
                }

                $setter = $this->resolveMethod($methodRequestReflection, 'set' . ucfirst($propertyName));
                if ($setter !== null) {
                    $requestSetters[$propertyName] = $setter;
                }

                $adder = $this->resolveAdder($methodRequestReflection, $propertyName);
                if ($adder !== null) {
                    [$adderMethod, $adderElementType] = $adder;
                    $requestAdders[$propertyName] = $adderMethod;
                    $allParameters[$index]['type'] = $adderElementType;
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

    /**
     * Finds the property's getter by exact name, trying `getX` then `isX`
     * then the bare accessor `x` (needed for a boolean property such as
     * `$isActive` whose getter is `isActive()`).
     */
    private function resolveGetter(ReflectionClass $reflection, string $propertyName): ?string
    {
        $candidates = [];
        foreach (self::GETTER_PREFIXES as $prefix) {
            $candidates[] = $prefix . ucfirst($propertyName);
        }
        $candidates[] = $propertyName;

        foreach ($candidates as $candidate) {
            $resolved = $this->resolveMethod($reflection, $candidate);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * A property holding a collection is expected to be named as the plural
     * of the adder's own argument, e.g. property `$tokens` is filled element
     * by element through `addToken(Token $token)`. The singular form is
     * derived with an English inflector rather than by dropping the last
     * character, so irregular plurals resolve correctly: `$children` to
     * `addChild(Child $child)`, `$people` to `addPerson(Person $person)`.
     * Returns the adder's method name and the element type taken from its
     * own parameter, or null when the property has no matching adder.
     *
     * @return array{0: string, 1: ?string}|null
     */
    private function resolveAdder(ReflectionClass $reflection, string $propertyName): ?array
    {
        foreach ($this->inflector->singularize($propertyName) as $singularName) {
            $adder = $this->resolveMethod($reflection, 'add' . ucfirst($singularName));
            if ($adder !== null) {
                $adderParameterType = $reflection->getMethod($adder)->getParameters()[0]->getType();
                $adderElementType = $adderParameterType instanceof ReflectionNamedType ? $adderParameterType->getName() : null;

                return [$adder, $adderElementType];
            }
        }

        return null;
    }

    private function resolveMethod(ReflectionClass $reflection, string $methodName): ?string
    {
        if ($reflection->hasMethod($methodName) && $reflection->getMethod($methodName)->isPublic()) {
            return $reflection->getMethod($methodName)->getName();
        }

        return null;
    }

    /**
     * Decides whether call() can return a response that must bypass JSON-RPC wrapping.
     *
     * Both shapes of return type are examined. Only unions used to be, so the simplest way to write
     * such a method - `public function call(Request $r): Png` with a single named type - was never
     * recognised, and the binary response was quietly serialised into JSON as an object with
     * `content`, `statusCode` and `charset` members. No error, no warning; the caller received a
     * JSON envelope where a file was expected. The documented example happens to use a union, which
     * is why this went unnoticed, and every test set the resulting flag by hand rather than letting
     * this method produce it.
     */
    private function detectPlainResponse(ReflectionClass $methodReflectionClass): bool
    {
        $callResponseType = $methodReflectionClass->getMethod('call')->getReturnType();

        $candidates = $callResponseType instanceof ReflectionUnionType
            ? $callResponseType->getTypes()
            : [$callResponseType];

        foreach ($candidates as $type) {
            if (!$type instanceof ReflectionNamedType) {
                continue;
            }

            $typeName = $type->getName();
            if (!class_exists($typeName) && !interface_exists($typeName)) {
                continue;
            }

            if (is_a($typeName, PlainResponseInterface::class, true)) {
                return true;
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
            // Properties are vetted by getValidatorsForRequest() before this runs, but constructor
            // parameters arrive here unvetted - and a DTO may well declare `private int $limit`
            // while its constructor takes a bare `$limit`. Reading getName() off the null that
            // getType() returns for those aborted the container build with "Call to a member
            // function getName() on null": no parameter named, no class named, nothing to act on.
            $type = $property->getType();

            if (!$type instanceof ReflectionNamedType) {
                throw new Exception(
                    sprintf(
                        'Parameter %s of class %s %s; only a single named type is supported.',
                        $property->getName(),
                        $property->getDeclaringClass()?->getName() ?? 'unknown',
                        $type === null ? 'has no declared type' : sprintf('has type %s, which is not a single named type', (string) $type),
                    ),
                );
            }

            $propData = [
                'name' => $property->getName(),
                'type' => $type->getName(),
            ];

            $method = $property instanceof ReflectionProperty ? 'hasDefaultValue' : 'isDefaultValueAvailable';

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

        $methods = $requestReflection->getMethods();
        $properties = $requestReflection->getProperties();

        $propertiesIdx = [];
        foreach ($properties as $property) {
            $propertyType = $property->getType();

            if ($propertyType === null) {
                throw new Exception(
                    sprintf(
                        'Property %s of class %s has no declared type',
                        $property->getName(),
                        $requestReflection->getName(),
                    ),
                );
            }

            if (!$propertyType instanceof ReflectionNamedType) {
                throw new Exception(
                    sprintf(
                        'Property %s of class %s has type %s, which is not supported; only a single named type is supported.',
                        $property->getName(),
                        $requestReflection->getName(),
                        (string) $propertyType,
                    ),
                );
            }

            $propertiesIdx[$property->getName()] = [
                'type' => $propertyType->getName(),
                'allowsNull' => $propertyType->allowsNull() || $property->hasDefaultValue(),
            ];
        }

        $methodsIdx = [];
        foreach ($methods as $method) {
            $methodsIdx[$method->getName()] = $method;
        }

        foreach ($propertiesIdx as $name => $typeData) {
            $property = $requestReflection->getProperty($name);
            $isPublicProperty = $property->isPublic();
            // Same candidate list as resolveGetter(), and for a reason: this loop runs first, so a
            // single rigid name here decided the outcome no matter what resolveGetter() would have
            // accepted. A boolean $isActive whose getter is isActive() aborted the build demanding
            // isIsActive(), and the bare accessor the other resolver documents was unreachable in
            // practice. One rule for what counts as a getter, applied in both places.
            $getterName = $this->resolveGetter($requestReflection, $name);

            if (!$isPublicProperty && ($getterName === null || !isset($methodsIdx[$getterName]))) {
                throw new Exception(
                    sprintf(
                        'Property %s of class %s has no accessible getter (expected one of get%s, is%s, or %s)',
                        $name,
                        $requestReflection->getName(),
                        ucfirst($name),
                        ucfirst($name),
                        $name,
                    ),
                );
            }
            $getter = $getterName !== null ? ($methodsIdx[$getterName] ?? null) : null;

            $setterName = 'set' . ucfirst($name);
            if (!$isPublicProperty && !isset($methodsIdx[$setterName])) {
                throw new Exception(
                    sprintf(
                        'Property %s of class %s has no method %s',
                        $name,
                        $requestReflection->getName(),
                        $setterName,
                    ),
                );
            }
            $setter = $methodsIdx[$setterName] ?? null;
            if ($setter === null) {
                $validatorsIdx[$name] = $typeData;

                continue;
            }
            $setterParamType = $setter->getParameters()[0]->getType();
            if ($setterParamType === null) {
                continue;
            }
            if (!$setterParamType instanceof ReflectionNamedType || $setterParamType->getName() !== $typeData['type']) {
                throw new Exception(
                    sprintf(
                        'Property %s of method %s has invalid data type in setter %s',
                        $name,
                        $requestReflection->getName(),
                        $setter->getName(),
                    ),
                );
            }
            if ($getter === null) {
                $validatorsIdx[$name] = $typeData;

                continue;
            }

            $getterReturnType = $getter->getReturnType();
            if (!$getterReturnType instanceof ReflectionNamedType || $getterReturnType->getName() !== $typeData['type']) {
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
