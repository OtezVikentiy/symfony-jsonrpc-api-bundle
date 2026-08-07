<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Spec;

use OV\JsonRPCAPIBundle\Core\JRPCException;
use OV\JsonRPCAPIBundle\Core\Logging\NullJsonRpcCallLogger;
use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use OV\JsonRPCAPIBundle\Core\Services\ErrorSanitizer;
use OV\JsonRPCAPIBundle\Core\Services\HeadersPreparer;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler;
use OV\JsonRPCAPIBundle\Core\Services\ResponseService;
use OV\JsonRPCAPIBundle\DependencyInjection\CompilerPass;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class HydrationDetailsChild
{
    private string $name = '';

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}

final class HydrationDetailsPerson
{
    private string $name = '';

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}

final class HydrationDetailsCollectionsRequest
{
    private array $children = [];
    private array $people = [];
    private array $tokens = [];

    public function getChildren(): array
    {
        return $this->children;
    }

    public function setChildren(array $children): void
    {
        $this->children = $children;
    }

    public function addChild(HydrationDetailsChild $child): void
    {
        $this->children[] = $child;
    }

    public function getPeople(): array
    {
        return $this->people;
    }

    public function setPeople(array $people): void
    {
        $this->people = $people;
    }

    public function addPerson(HydrationDetailsPerson $person): void
    {
        $this->people[] = $person;
    }

    public function getTokens(): array
    {
        return $this->tokens;
    }

    public function setTokens(array $tokens): void
    {
        $this->tokens = $tokens;
    }

    public function addToken(string $token): void
    {
        $this->tokens[] = $token;
    }
}

final class HydrationDetailsCollectionsMethod
{
    public ?HydrationDetailsCollectionsRequest $captured = null;

    public function call(HydrationDetailsCollectionsRequest $request): array
    {
        $this->captured = $request;

        return ['ok' => true];
    }
}

final class HydrationDetailsAddress
{
    private ?string $city = null;

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): void
    {
        $this->city = $city;
    }
}

final class HydrationDetailsNestedRequest
{
    private ?int $id = null;
    private ?HydrationDetailsAddress $address = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getAddress(): ?HydrationDetailsAddress
    {
        return $this->address;
    }

    public function setAddress(?HydrationDetailsAddress $address): void
    {
        $this->address = $address;
    }
}

final class HydrationDetailsNestedMethod
{
    public ?HydrationDetailsNestedRequest $captured = null;

    public function call(HydrationDetailsNestedRequest $request): array
    {
        $this->captured = $request;

        return ['ok' => true];
    }
}

/**
 * Two independent details of DTO hydration:
 *
 * - The adder lookup used to guess a singular by dropping the last
 *   character of the property name (`products` -> `product`), which broke
 *   for irregular plurals (`children` -> `childre`, `people` -> `peopl`).
 *   CompilerPass now indexes adders by the property name itself and
 *   RequestHandler looks them up the same way on both sides.
 * - `allowExtraFields` used to apply only to the top-level DTO; a nested
 *   DTO always rejected unexpected fields regardless of the setting.
 */
final class HydrationDetailsTest extends TestCase
{
    public function testCompilerPassIndexesAddersByPropertyNameForIrregularPlurals(): void
    {
        $compilerPass = new CompilerPass(new CamelCaseToSnakeCaseNameConverter());
        $reflectionMethod = new ReflectionMethod(CompilerPass::class, 'analyzeRequestClass');
        $analyze = $reflectionMethod->getClosure($compilerPass);

        $result = $analyze(
            new ReflectionClass(HydrationDetailsCollectionsMethod::class),
            HydrationDetailsCollectionsMethod::class,
        );

        $this->assertSame('addChild', $result['requestAdders']['children']);
        $this->assertSame('addPerson', $result['requestAdders']['people']);
        $this->assertSame('addToken', $result['requestAdders']['tokens']);
        $this->assertArrayNotHasKey('childre', $result['requestAdders']);
        $this->assertArrayNotHasKey('peopl', $result['requestAdders']);
        $this->assertArrayNotHasKey('token', $result['requestAdders']);
    }

    public function testIrregularPluralChildrenAdderPopulatesCollection(): void
    {
        $captured = $this->dispatchCollections([
            'children' => [['name' => 'Alice'], ['name' => 'Bob']],
        ]);

        $this->assertCount(2, $captured->getChildren(), 'addChild() must be called for every element of "children"');
        $this->assertInstanceOf(HydrationDetailsChild::class, $captured->getChildren()[0]);
        $this->assertSame('Alice', $captured->getChildren()[0]->getName());
        $this->assertSame('Bob', $captured->getChildren()[1]->getName());
    }

    public function testIrregularPluralPeopleAdderPopulatesCollection(): void
    {
        $captured = $this->dispatchCollections([
            'people' => [['name' => 'Amy']],
        ]);

        $this->assertCount(1, $captured->getPeople(), 'addPerson() must be called for every element of "people"');
        $this->assertInstanceOf(HydrationDetailsPerson::class, $captured->getPeople()[0]);
        $this->assertSame('Amy', $captured->getPeople()[0]->getName());
    }

    public function testRegularPluralTokensAdderStillPopulatesCollection(): void
    {
        $captured = $this->dispatchCollections([
            'tokens' => ['a', 'b', 'c'],
        ]);

        $this->assertSame(['a', 'b', 'c'], $captured->getTokens());
    }

    public function testAllowExtraFieldsTrueLetsNestedDtoHydrateWithKnownFields(): void
    {
        $method = new HydrationDetailsNestedMethod();
        $this->dispatchNestedWithMethod(
            $method,
            allowExtraFields: true,
            params: ['id' => 1, 'address' => ['city' => 'Moscow', 'extraField' => 'ignored']],
        );

        $this->assertInstanceOf(HydrationDetailsNestedRequest::class, $method->captured);
        $this->assertSame('Moscow', $method->captured->getAddress()->getCity());
    }

    public function testAllowExtraFieldsFalseStillRejectsUnexpectedFieldInNestedDto(): void
    {
        $method = new HydrationDetailsNestedMethod();
        $response = $this->dispatchNestedWithMethod(
            $method,
            allowExtraFields: false,
            params: ['id' => 1, 'address' => ['city' => 'Moscow', 'extraField' => 'not allowed']],
        );

        $this->assertNull($method->captured, 'Hydration must fail before the method processor is ever invoked');
        $this->assertInstanceOf(JsonResponse::class, $response);

        $decoded = json_decode((string) $response->getContent(), true);
        $this->assertSame(JRPCException::INVALID_PARAMS, $decoded['error']['code']);
        $this->assertStringContainsString('extraField', $decoded['error']['message']);
    }

    private function dispatchCollections(array $params): HydrationDetailsCollectionsRequest
    {
        $method = new HydrationDetailsCollectionsMethod();

        $methodSpec = new MethodSpec(
            methodClass: HydrationDetailsCollectionsMethod::class,
            requestType: 'POST',
            methodName: 'collections',
            requestMetadata: new RequestMetadata(
                request: HydrationDetailsCollectionsRequest::class,
                allParameters: [
                    ['name' => 'children', 'type' => HydrationDetailsChild::class],
                    ['name' => 'people', 'type' => HydrationDetailsPerson::class],
                    ['name' => 'tokens', 'type' => 'string'],
                ],
                requiredParameters: [],
                requestGetters: [
                    'children' => 'getChildren',
                    'people' => 'getPeople',
                    'tokens' => 'getTokens',
                ],
                // "children" and "people" deliberately have no setter mapping here,
                // even though the DTO has setChildren()/setPeople(): if the adder
                // lookup regresses, hydration must fall through to nothing and
                // leave the collection empty, not silently accept the raw
                // unhydrated array through a setter and mask the regression.
                requestSetters: [
                    'tokens' => 'setTokens',
                ],
                requestAdders: [
                    'children' => 'addChild',
                    'people' => 'addPerson',
                    'tokens' => 'addToken',
                ],
                validators: [],
            ),
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: false),
        );

        $specCollection = new MethodSpecCollection();
        $specCollection->addMethodSpec(1, 'collections', $methodSpec);

        $handler = $this->buildHandler($specCollection, HydrationDetailsCollectionsMethod::class, $method);

        $handler->processBatch([
            'jsonrpc' => '2.0',
            'method' => 'collections',
            'params' => $params,
            'id' => '1',
        ], 1, 'POST');

        return $method->captured;
    }

    private function dispatchNestedWithMethod(HydrationDetailsNestedMethod $method, bool $allowExtraFields, array $params): mixed
    {
        $methodSpec = new MethodSpec(
            methodClass: HydrationDetailsNestedMethod::class,
            requestType: 'POST',
            methodName: 'nested',
            requestMetadata: new RequestMetadata(
                request: HydrationDetailsNestedRequest::class,
                allParameters: [
                    ['name' => 'id', 'type' => 'int'],
                    ['name' => 'address', 'type' => HydrationDetailsAddress::class],
                ],
                requiredParameters: [],
                requestGetters: ['id' => 'getId', 'address' => 'getAddress'],
                requestSetters: ['id' => 'setId', 'address' => 'setAddress'],
                requestAdders: [],
                validators: [],
            ),
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: false),
        );

        $specCollection = new MethodSpecCollection();
        $specCollection->addMethodSpec(1, 'nested', $methodSpec);

        $handler = $this->buildHandler($specCollection, HydrationDetailsNestedMethod::class, $method, $allowExtraFields);

        return $handler->processBatch([
            'jsonrpc' => '2.0',
            'method' => 'nested',
            'params' => $params,
            'id' => '1',
        ], 1, 'POST');
    }

    private function buildHandler(
        MethodSpecCollection $specCollection,
        string $methodClass,
        object $methodInstance,
        bool $allowExtraFields = false,
    ): RequestHandler {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturn(true);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $headersPreparer = new HeadersPreparer(['*']);
        $responseService = new ResponseService($headersPreparer, new ErrorSanitizer());

        $container = $this->createMock(Container::class);
        $container->method('get')->willReturnMap([
            [$methodClass, 1, $methodInstance],
        ]);

        return new RequestHandler(
            $security,
            $specCollection,
            $validator,
            $headersPreparer,
            $container,
            $responseService,
            new NullJsonRpcCallLogger(),
            allowExtraFields: $allowExtraFields,
        );
    }
}
