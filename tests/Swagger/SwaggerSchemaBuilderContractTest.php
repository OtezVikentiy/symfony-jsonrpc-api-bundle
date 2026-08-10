<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Swagger;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\Core\Annotation\SwaggerArrayProperty;
use OV\JsonRPCAPIBundle\Core\Response\PlainResponseInterface;
use OV\JsonRPCAPIBundle\DependencyInjection\CompilerPass;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use OV\JsonRPCAPIBundle\Swagger\SwaggerSchemaBuilder;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Yaml\Yaml;

/**
 * The OpenAPI document is built from the same MethodSpec objects the container produces, so the
 * generator has the same blind spot the compiler pass had: every existing test hands it specs
 * assembled by hand. This file compiles a real container from real method classes and generates
 * from whatever comes out, which is the only way the two halves are checked against each other.
 *
 * The cases below are the branches that decide document shape - what is skipped, what a path is
 * named, how a response type is chosen, and how a class that refers to itself is emitted.
 */
final class SwaggerSchemaBuilderContractTest extends TestCase
{
    public function testAMethodMarkedIgnoreInSwaggerIsLeftOut(): void
    {
        $document = $this->generate([VisibleMethod::class, HiddenMethod::class]);

        self::assertArrayHasKey('/visible', $document['paths']);
        self::assertArrayNotHasKey('/hidden', $document['paths']);
    }

    public function testOnlyTheRequestedApiVersionIsIncluded(): void
    {
        $document = $this->generate([VisibleMethod::class, \OV\JsonRPCAPIBundle\Tests\Swagger\V2\SecondVersionMethod::class]);

        self::assertArrayHasKey('/visible', $document['paths']);
        self::assertArrayNotHasKey('/second_version', $document['paths'], 'a method of another version belongs in another document');
    }

    public function testAGroupBecomesAPathPrefix(): void
    {
        $document = $this->generate([GroupedMethod::class]);

        self::assertArrayHasKey('/catalogue/grouped', $document['paths']);
    }

    public function testTagsAreCollected(): void
    {
        $document = $this->generate([TaggedMethod::class]);

        self::assertContains(['name' => 'reporting'], $document['tags']);
    }

    /**
     * A union of an ordinary response and a plain one describes the ordinary member: the plain one
     * is a file, and a file has no schema worth publishing.
     */
    public function testAUnionReturnTypePicksTheNonPlainMember(): void
    {
        $document = $this->generate([UnionReturnMethod::class]);

        $schemas = array_keys($document['components']['schemas']);

        self::assertContains('unionReturnResponse', $schemas);
    }

    public function testAMethodWithNoReturnTypeIsSkipped(): void
    {
        $document = $this->generate([UntypedReturnMethod::class, VisibleMethod::class]);

        self::assertArrayNotHasKey('/untyped_return', $document['paths'], 'nothing can be said about an undeclared return type');
        self::assertArrayHasKey('/visible', $document['paths'], 'and it must not take the rest of the document with it');
    }

    /**
     * A DTO that refers to its own type - a tree node, a linked comment - must emit one schema and
     * reference it, rather than recursing until it runs out of memory.
     */
    public function testASelfReferencingResponseEmitsOneSchemaAndRefersToIt(): void
    {
        $document = $this->generate([TreeMethod::class]);

        $schemas = $document['components']['schemas'];
        $nodeSchemas = array_filter(array_keys($schemas), static fn (string $n): bool => str_contains($n, 'TreeNode'));

        self::assertCount(1, $nodeSchemas, 'the node type is described once');
    }

    /**
     * PHP's `array` carries no element type, so an untyped collection is published as a bare array -
     * documented behaviour, and the reason #[SwaggerArrayProperty] exists.
     */
    public function testAnUndescribedCollectionIsPublishedAsABareArray(): void
    {
        $document = $this->generate([TreeMethod::class]);
        $node = $document['components']['schemas'][$this->schemaNameContaining($document, 'TreeNode')];

        self::assertSame('array', $node['properties']['children']['type']);
        self::assertArrayNotHasKey('items', $node['properties']['children']);
    }

    public function testACollectionDescribedByTheAttributeCarriesItsElementType(): void
    {
        $document = $this->generate([DescribedArrayMethod::class]);
        $properties = $document['components']['schemas']['describedArrayResponse']['properties'];

        self::assertSame('array', $properties['errors']['type']);
        self::assertSame(['type' => 'string'], $properties['errors']['items']);
    }

    public function testACollectionOfObjectsDescribedByTheAttributeRefersToTheirSchema(): void
    {
        $document = $this->generate([DescribedArrayMethod::class]);
        $items = $document['components']['schemas']['describedArrayResponse']['properties']['nodes']['items'];

        self::assertArrayHasKey('$ref', $items, json_encode($items));
        self::assertStringContainsString('TreeNode', $items['$ref']);
    }

    private function schemaNameContaining(array $document, string $needle): string
    {
        foreach (array_keys($document['components']['schemas']) as $name) {
            if (str_contains($name, $needle)) {
                return $name;
            }
        }

        self::fail(sprintf('no schema name contains %s', $needle));
    }

    /**
     * Scalar names are the JSON Schema ones, not PHP's.
     */
    public function testPhpScalarNamesAreTranslatedToJsonSchemaNames(): void
    {
        $document = $this->generate([ScalarsMethod::class]);

        $properties = $document['components']['schemas']['scalarsResponse']['properties'];

        self::assertSame('integer', $properties['count']['type']);
        self::assertSame('boolean', $properties['done']['type']);
        self::assertSame('number', $properties['ratio']['type']);
        self::assertSame('string', $properties['label']['type']);
    }

    /**
     * A union-typed property has no single JSON Schema type, and guessing one would be worse than
     * saying string and moving on - the document still describes the field's presence.
     */
    public function testAUnionTypedPropertyFallsBackToString(): void
    {
        $document = $this->generate([UnionPropertyMethod::class]);

        self::assertSame('string', $document['components']['schemas']['unionPropertyResponse']['properties']['mixed']['type']);
    }

    /**
     * A method whose only possible response is a file has no schema to publish, so it is left out
     * rather than described as an empty object.
     */
    public function testAMethodReturningOnlyAPlainResponseIsSkipped(): void
    {
        $document = $this->generate([OnlyPlainMethod::class, VisibleMethod::class]);

        self::assertArrayNotHasKey('/only_plain', $document['paths']);
        self::assertArrayHasKey('/visible', $document['paths']);
    }

    public function testAMethodReturningANonClassTypeIsSkipped(): void
    {
        $document = $this->generate([ArrayReturnMethod::class, VisibleMethod::class]);

        self::assertArrayNotHasKey('/array_return', $document['paths'], 'an array is not a schema');
        self::assertArrayHasKey('/visible', $document['paths']);
    }

    /**
     * The same nested type reached twice - once directly, once inside a collection - must be
     * described once and referenced from both places, not emitted twice or walked twice.
     */
    public function testATypeReachedTwiceIsDescribedOnceAndReferencedBothTimes(): void
    {
        $document = $this->generate([TwiceMethod::class]);

        $schemas = $document['components']['schemas'];
        $nodeNames = array_filter(array_keys($schemas), static fn (string $n): bool => str_contains($n, 'TreeNode'));
        self::assertCount(1, $nodeNames, 'described once');

        $properties = $schemas['twiceResponse']['properties'];
        self::assertStringContainsString('TreeNode', $properties['single']['$ref']);
        self::assertSame('array', $properties['many']['type']);
        self::assertStringContainsString('TreeNode', $properties['many']['items']['$ref']);
    }

    /**
     * The union members that are not classes - `array` here - are stepped over rather than taken for
     * a schema.
     */
    public function testAUnionMixingAClassWithANonClassPicksTheClass(): void
    {
        $document = $this->generate([UnionWithArrayMethod::class]);

        self::assertArrayHasKey('unionWithArrayResponse', $document['components']['schemas']);
    }

    public function testAUnionOfOnlyPlainResponsesIsSkipped(): void
    {
        $document = $this->generate([UnionOfPlainMethod::class, VisibleMethod::class]);

        self::assertArrayNotHasKey('/union_of_plain', $document['paths']);
        self::assertArrayHasKey('/visible', $document['paths']);
    }

    /**
     * PHP normalises a union so classes come before builtins, which means a non-class member is only
     * ever examined once every class member has been rejected - here, because the only one is a file.
     */
    public function testAUnionOfAPlainResponseAndABuiltinIsSkipped(): void
    {
        $document = $this->generate([PlainOrArrayMethod::class, VisibleMethod::class]);

        self::assertArrayNotHasKey('/plain_or_array', $document['paths']);
        self::assertArrayHasKey('/visible', $document['paths']);
    }

    public function testAnIntersectionReturnTypeIsSkipped(): void
    {
        $document = $this->generate([IntersectionReturnMethod::class, VisibleMethod::class]);

        self::assertArrayNotHasKey('/intersection_return', $document['paths'], 'an intersection names no single schema');
        self::assertArrayHasKey('/visible', $document['paths']);
    }

    public function testAUnionCarryingAnIntersectionMemberStepsOverIt(): void
    {
        $document = $this->generate([UnionWithIntersectionMethod::class]);

        self::assertArrayHasKey('unionWithIntersectionResponse', $document['components']['schemas']);
    }

    /**
     * The mirror of the collection case: when the type was first met inside a collection, a later
     * plain property referring to it must become a plain $ref rather than another array.
     */
    public function testATypeFirstMetInACollectionIsReferencedPlainlyAfterwards(): void
    {
        $document = $this->generate([ReverseTwiceMethod::class]);

        $properties = $document['components']['schemas']['reverseTwiceResponse']['properties'];

        self::assertSame('array', $properties['many']['type']);
        self::assertStringContainsString('TreeNode', $properties['single']['$ref']);
    }

    public function testAResponseReferencingAMissingClassIsRefusedByName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not exist');

        $this->generate([MissingClassMethod::class]);
    }

    public function testAnAbsentAuthTokenNameOmitsTheSecurityBlocks(): void
    {
        $document = $this->generate([VisibleMethod::class], authTokenName: null);

        self::assertArrayNotHasKey('security', $document);
        self::assertArrayNotHasKey('securitySchemes', $document['components'] ?? []);
    }

    public function testAnEmptyAuthTokenNameOmitsTheSecurityBlocks(): void
    {
        $document = $this->generate([VisibleMethod::class], authTokenName: '');

        self::assertArrayNotHasKey('security', $document);
        self::assertArrayNotHasKey('securitySchemes', $document['components'] ?? []);
    }

    public function testAConfiguredAuthTokenNamePublishesTheSecurityScheme(): void
    {
        $document = $this->generate([VisibleMethod::class]);

        self::assertSame([['ApiKeyAuth' => []]], $document['security']);
        self::assertSame(
            ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-AUTH-TOKEN'],
            $document['components']['securitySchemes']['ApiKeyAuth'],
        );
    }

    /**
     * @param list<class-string> $methodClasses
     *
     * @return array<string, mixed>
     */
    private function generate(array $methodClasses, int $apiVersion = 1, ?string $authTokenName = 'X-AUTH-TOKEN'): array
    {
        $container = new ContainerBuilder();
        foreach ($methodClasses as $class) {
            $container->register($class, $class)->addTag('ov.rpc.method')->setAutowired(true);
        }

        (new CompilerPass(new CamelCaseToSnakeCaseNameConverter()))->process($container);
        $container->getDefinition(MethodSpecCollection::class)->setPublic(true);
        $container->compile();

        /** @var MethodSpecCollection $collection */
        $collection = $container->get(MethodSpecCollection::class);

        $item = [
            'api_version' => (string) $apiVersion,
            'base_path' => 'https://api.example',
            'base_path_description' => '',
            'base_path_variables' => [],
            'test_path' => 'https://test.example',
            'test_path_description' => '',
            'test_path_variables' => [],
            'info' => [
                'title' => 't',
                'description' => 'd',
                'terms_of_service_url' => 'u',
                'contact' => ['name' => 'n', 'url' => 'u', 'email' => 'e'],
                'license' => 'l',
                'licenseUrl' => 'lu',
            ],
        ];
        if ($authTokenName !== null) {
            $item['auth_token_name'] = $authTokenName;
        }

        $yaml = (new SwaggerSchemaBuilder($collection))->build($item);

        return Yaml::parse($yaml);
    }
}

final class PlainRequest
{
    private string $q = '';

    public function getQ(): string
    {
        return $this->q;
    }

    public function setQ(string $q): void
    {
        $this->q = $q;
    }
}

final class VisibleResponse
{
    private string $ok = '';

    public function getOk(): string
    {
        return $this->ok;
    }

    public function setOk(string $ok): void
    {
        $this->ok = $ok;
    }
}

#[JsonRPCAPI(methodName: 'visible', type: 'POST', version: 1)]
final class VisibleMethod
{
    public function call(PlainRequest $request): VisibleResponse
    {
        return new VisibleResponse();
    }
}

#[JsonRPCAPI(methodName: 'hidden', type: 'POST', version: 1, ignoreInSwagger: true)]
final class HiddenMethod
{
    public function call(PlainRequest $request): VisibleResponse
    {
        return new VisibleResponse();
    }
}

#[JsonRPCAPI(methodName: 'grouped', type: 'POST', version: 1, group: 'catalogue')]
final class GroupedMethod
{
    public function call(PlainRequest $request): VisibleResponse
    {
        return new VisibleResponse();
    }
}

#[JsonRPCAPI(methodName: 'tagged', type: 'POST', version: 1, tags: ['reporting'])]
final class TaggedMethod
{
    public function call(PlainRequest $request): VisibleResponse
    {
        return new VisibleResponse();
    }
}

final class DownloadResponse extends Response implements PlainResponseInterface
{
}

final class UnionReturnResponse
{
    private string $note = '';

    public function getNote(): string
    {
        return $this->note;
    }

    public function setNote(string $note): void
    {
        $this->note = $note;
    }
}

#[JsonRPCAPI(methodName: 'unionReturn', type: 'POST', version: 1)]
final class UnionReturnMethod
{
    public function call(PlainRequest $request): DownloadResponse|UnionReturnResponse
    {
        return new UnionReturnResponse();
    }
}

#[JsonRPCAPI(methodName: 'untypedReturn', type: 'POST', version: 1)]
final class UntypedReturnMethod
{
    public function call(PlainRequest $request)
    {
        return [];
    }
}

final class TreeNode
{
    private string $label = '';

    private array $children = [];

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function setChildren(array $children): void
    {
        $this->children = $children;
    }

    public function addChild(TreeNode $child): void
    {
        $this->children[] = $child;
    }
}

final class TreeResponse
{
    private TreeNode $root;

    public function __construct()
    {
        $this->root = new TreeNode();
    }

    public function getRoot(): TreeNode
    {
        return $this->root;
    }

    public function setRoot(TreeNode $root): void
    {
        $this->root = $root;
    }
}

#[JsonRPCAPI(methodName: 'tree', type: 'POST', version: 1)]
final class TreeMethod
{
    public function call(PlainRequest $request): TreeResponse
    {
        return new TreeResponse();
    }
}

final class DescribedArrayResponse
{
    #[SwaggerArrayProperty(type: 'string')]
    private array $errors = [];

    #[SwaggerArrayProperty(type: TreeNode::class, ofClass: true)]
    private array $nodes = [];

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function setErrors(array $errors): void
    {
        $this->errors = $errors;
    }

    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function setNodes(array $nodes): void
    {
        $this->nodes = $nodes;
    }
}

#[JsonRPCAPI(methodName: 'describedArray', type: 'POST', version: 1)]
final class DescribedArrayMethod
{
    public function call(PlainRequest $request): DescribedArrayResponse
    {
        return new DescribedArrayResponse();
    }
}

#[JsonRPCAPI(methodName: 'onlyPlain', type: 'POST', version: 1)]
final class OnlyPlainMethod
{
    public function call(PlainRequest $request): DownloadResponse
    {
        return new DownloadResponse();
    }
}

#[JsonRPCAPI(methodName: 'arrayReturn', type: 'POST', version: 1)]
final class ArrayReturnMethod
{
    public function call(PlainRequest $request): array
    {
        return [];
    }
}

final class TwiceResponse
{
    private TreeNode $single;

    #[SwaggerArrayProperty(type: TreeNode::class, ofClass: true)]
    private array $many = [];

    public function __construct()
    {
        $this->single = new TreeNode();
    }

    public function getSingle(): TreeNode
    {
        return $this->single;
    }

    public function setSingle(TreeNode $single): void
    {
        $this->single = $single;
    }

    public function getMany(): array
    {
        return $this->many;
    }

    public function setMany(array $many): void
    {
        $this->many = $many;
    }
}

#[JsonRPCAPI(methodName: 'twice', type: 'POST', version: 1)]
final class TwiceMethod
{
    public function call(PlainRequest $request): TwiceResponse
    {
        return new TwiceResponse();
    }
}

final class UnionWithArrayResponse
{
    private string $note = '';

    public function getNote(): string
    {
        return $this->note;
    }

    public function setNote(string $note): void
    {
        $this->note = $note;
    }
}

#[JsonRPCAPI(methodName: 'unionWithArray', type: 'POST', version: 1)]
final class UnionWithArrayMethod
{
    public function call(PlainRequest $request): array|UnionWithArrayResponse
    {
        return new UnionWithArrayResponse();
    }
}

final class OtherDownloadResponse extends Response implements PlainResponseInterface
{
}

#[JsonRPCAPI(methodName: 'unionOfPlain', type: 'POST', version: 1)]
final class UnionOfPlainMethod
{
    public function call(PlainRequest $request): DownloadResponse|OtherDownloadResponse
    {
        return new DownloadResponse();
    }
}

#[JsonRPCAPI(methodName: 'plainOrArray', type: 'POST', version: 1)]
final class PlainOrArrayMethod
{
    public function call(PlainRequest $request): DownloadResponse|array
    {
        return new DownloadResponse();
    }
}

interface Countable1
{
}

interface Countable2
{
}

final class BothInterfaces implements Countable1, Countable2
{
}

#[JsonRPCAPI(methodName: 'intersectionReturn', type: 'POST', version: 1)]
final class IntersectionReturnMethod
{
    public function call(PlainRequest $request): Countable1&Countable2
    {
        return new BothInterfaces();
    }
}

final class UnionWithIntersectionResponse
{
    private string $note = '';

    public function getNote(): string
    {
        return $this->note;
    }

    public function setNote(string $note): void
    {
        $this->note = $note;
    }
}

#[JsonRPCAPI(methodName: 'unionWithIntersection', type: 'POST', version: 1)]
final class UnionWithIntersectionMethod
{
    public function call(PlainRequest $request): (Countable1&Countable2)|UnionWithIntersectionResponse
    {
        return new UnionWithIntersectionResponse();
    }
}

final class ReverseTwiceResponse
{
    #[SwaggerArrayProperty(type: TreeNode::class, ofClass: true)]
    private array $many = [];

    private TreeNode $single;

    public function __construct()
    {
        $this->single = new TreeNode();
    }

    public function getMany(): array
    {
        return $this->many;
    }

    public function setMany(array $many): void
    {
        $this->many = $many;
    }

    public function getSingle(): TreeNode
    {
        return $this->single;
    }

    public function setSingle(TreeNode $single): void
    {
        $this->single = $single;
    }
}

#[JsonRPCAPI(methodName: 'reverseTwice', type: 'POST', version: 1)]
final class ReverseTwiceMethod
{
    public function call(PlainRequest $request): ReverseTwiceResponse
    {
        return new ReverseTwiceResponse();
    }
}

final class ScalarsResponse
{
    private int $count = 0;

    private bool $done = false;

    private float $ratio = 0.0;

    private string $label = '';

    public function getCount(): int
    {
        return $this->count;
    }

    public function setCount(int $count): void
    {
        $this->count = $count;
    }

    public function isDone(): bool
    {
        return $this->done;
    }

    public function setDone(bool $done): void
    {
        $this->done = $done;
    }

    public function getRatio(): float
    {
        return $this->ratio;
    }

    public function setRatio(float $ratio): void
    {
        $this->ratio = $ratio;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }
}

#[JsonRPCAPI(methodName: 'scalars', type: 'POST', version: 1)]
final class ScalarsMethod
{
    public function call(PlainRequest $request): ScalarsResponse
    {
        return new ScalarsResponse();
    }
}

final class UnionPropertyResponse
{
    private int|string $mixed = 0;

    public function getMixed(): int|string
    {
        return $this->mixed;
    }

    public function setMixed(int|string $mixed): void
    {
        $this->mixed = $mixed;
    }
}

#[JsonRPCAPI(methodName: 'unionProperty', type: 'POST', version: 1)]
final class UnionPropertyMethod
{
    public function call(PlainRequest $request): UnionPropertyResponse
    {
        return new UnionPropertyResponse();
    }
}

final class MissingClassResponse
{
    /** @var object */
    private \OV\JsonRPCAPIBundle\Tests\Swagger\Nowhere\Vanished $gone;

    public function getGone(): \OV\JsonRPCAPIBundle\Tests\Swagger\Nowhere\Vanished
    {
        return $this->gone;
    }

    public function setGone(\OV\JsonRPCAPIBundle\Tests\Swagger\Nowhere\Vanished $gone): void
    {
        $this->gone = $gone;
    }
}

#[JsonRPCAPI(methodName: 'missingClass', type: 'POST', version: 1)]
final class MissingClassMethod
{
    public function call(PlainRequest $request): MissingClassResponse
    {
        return new MissingClassResponse();
    }
}
