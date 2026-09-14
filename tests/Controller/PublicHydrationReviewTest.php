<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Controller;

use Exception;
use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\Core\Request\PartialRequestInterface;
use OV\JsonRPCAPIBundle\Core\Request\TracksProvidedFieldsTrait;
use OV\JsonRPCAPIBundle\DependencyInjection\CompilerPass;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

final class PublicHydrationReviewTest extends AbstractControllerTestCase
{
    protected bool $useRealValidator = true;

    private function compiledSpec(string $method): MethodSpec
    {
        $container = new ContainerBuilder();
        $container->register($method, $method)->addTag('ov.rpc.method');
        (new CompilerPass(new CamelCaseToSnakeCaseNameConverter()))->process($container);
        foreach ($container->getDefinitions() as $definition) {
            if ($definition->getClass() === MethodSpec::class) {
                $args = $definition->getArguments();
                $args[3] = new RequestMetadata(...$container->getDefinition((string) $args[3])->getArguments());
                $args[4] = new SwaggerMetadata(...$container->getDefinition((string) $args[4])->getArguments());

                return new MethodSpec(...$args);
            }
        }
        self::fail('Missing method spec');
    }

    public function testConstructorNameDoesNotDiscardAnUnassignedProperty(): void
    {
        $this->assertResult(CursorReviewMethod::class, ['cursor' => 'abc'], 'abc');
    }

    public function testEmptyPublicCollectionWithAdderIsInitialized(): void
    {
        $this->assertResult(CollectionReviewMethod::class, ['items' => []], []);
    }

    public function testConstructorBuiltObjectIsNotConvertedAgain(): void
    {
        $this->assertResult(FilterReviewMethod::class, ['filter' => ['label' => 'ready']], 'ready');
    }

    public function testPromotedPartialPositionalParamsReachConstructorAndAreTracked(): void
    {
        $this->assertResult(PositionalReviewMethod::class, ['first', 'second'], [['first', 'second'], true]);
    }

    public function testUntypedSetterKeepsPropertyValidator(): void
    {
        $this->assertResult(UntypedReviewMethod::class, ['label' => 'ready'], 'ready');
    }

    private function assertResult(string $method, array $params, mixed $expected): void
    {
        $spec = $this->compiledSpec($method);
        $response = $this->executeControllerTest(['jsonrpc' => '2.0', 'method' => $spec->getMethodName(), 'params' => $params, 'id' => 1], $spec);
        self::assertSame(['jsonrpc' => '2.0', 'result' => $expected, 'id' => 1], json_decode($response->getContent(), true));
    }

    public function testClassTypedPromotedPropertyIsRejectedAtCompileTime(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('has no accessible getter');
        $this->compiledSpec(PromotedObjectReviewMethod::class);
    }

    public function testGetterTypeIsCheckedWithoutSetter(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('invalid data type in getter');
        $this->compiledSpec(StaleGetterReviewMethod::class);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('restrictedProperties')]
    public function testPhp84RestrictedPropertyIsRejected(string $declaration, string $suffix): void
    {
        if (PHP_VERSION_ID < 80400) {
            self::markTestSkipped('PHP 8.4 property syntax');
        }
        $ns = __NAMESPACE__;
        eval("namespace $ns; class Restricted$suffix { $declaration } #[\\OV\\JsonRPCAPIBundle\\Core\\Annotation\\JsonRPCAPI(methodName: 'restricted', type: 'POST', version: 1)] class RestrictedMethod$suffix { public function call(Restricted$suffix \$request): array { return []; } }");
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('has no accessible getter');
        $this->compiledSpec($ns . '\\RestrictedMethod' . $suffix);
    }

    public static function restrictedProperties(): array
    {
        return [
            ['public private(set) string $status;', 'PrivateSet'],
            ['public protected(set) string $status;', 'ProtectedSet'],
            ['public string $status { get => "ready"; }', 'Hook'],
        ];
    }
}

class CursorReviewRequest
{
    public ?string $cursor = null;

    public function __construct(?string $cursor = null)
    {
    }
}
#[JsonRPCAPI(methodName: 'cursorReview', type: 'POST', version: 1)]
class CursorReviewMethod
{
    public function call(CursorReviewRequest $request): string
    {
        return $request->cursor;
    }
}
class CollectionReviewRequest
{
    public array $items;

    public function addItem(string $item): void
    {
        $this->items[] = $item;
    }
}
#[JsonRPCAPI(methodName: 'collectionReview', type: 'POST', version: 1)]
class CollectionReviewMethod
{
    public function call(CollectionReviewRequest $request): array
    {
        return $request->items;
    }
}
class ReviewFilter
{
    public function __construct(public string $label)
    {
    }
}
class FilterReviewRequest
{
    public ReviewFilter $filter;

    public function __construct(array $filter)
    {
        $this->filter = new ReviewFilter($filter['label']);
    }
}
#[JsonRPCAPI(methodName: 'filterReview', type: 'POST', version: 1)]
class FilterReviewMethod
{
    public function call(FilterReviewRequest $request): string
    {
        return $request->filter->label;
    }
}
class ReviewPartialBase implements PartialRequestInterface
{
    use TracksProvidedFieldsTrait;
}
class PositionalReviewRequest extends ReviewPartialBase
{
    public function __construct(public readonly array $params = [])
    {
    }
}
#[JsonRPCAPI(methodName: 'positionalReview', type: 'POST', version: 1)]
class PositionalReviewMethod
{
    public function call(PositionalReviewRequest $request): array
    {
        return [$request->params, $request->wasProvided('params')];
    }
}
class UntypedReviewRequest
{
    public string $label;

    public function setLabel($label): void
    {
        $this->label = $label;
    }
}
#[JsonRPCAPI(methodName: 'untypedReview', type: 'POST', version: 1)]
class UntypedReviewMethod
{
    public function call(UntypedReviewRequest $request): string
    {
        return $request->label;
    }
}
class PromotedObjectReviewRequest
{
    public function __construct(public readonly ReviewFilter $filter)
    {
    }
}
#[JsonRPCAPI(methodName: 'promotedObjectReview', type: 'POST', version: 1)]
class PromotedObjectReviewMethod
{
    public function call(PromotedObjectReviewRequest $request): array
    {
        return [];
    }
}
class StaleGetterReviewRequest
{
    public string $label;

    public function getLabel(): array
    {
        return [];
    }
}
#[JsonRPCAPI(methodName: 'staleGetterReview', type: 'POST', version: 1)]
class StaleGetterReviewMethod
{
    public function call(StaleGetterReviewRequest $request): array
    {
        return [];
    }
}
