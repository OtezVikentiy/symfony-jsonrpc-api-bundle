<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Core\Services;

use OV\JsonRPCAPIBundle\Core\Logging\NullJsonRpcCallLogger;
use OV\JsonRPCAPIBundle\Core\Services\ErrorSanitizer;
use OV\JsonRPCAPIBundle\Core\Services\HeadersPreparer;
use OV\JsonRPCAPIBundle\Core\Services\RequestHandler;
use OV\JsonRPCAPIBundle\Core\Services\ResponseService;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class SetterIndexCacheItemA
{
    private int $value = 0;

    public function getValue(): int
    {
        return $this->value;
    }

    public function setValue(int $value): void
    {
        $this->value = $value;
    }
}

final class SetterIndexCacheItemB
{
    private string $value = '';

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }
}

final class SetterIndexCacheRequest
{
    private array $itemsA = [];
    private array $itemsB = [];

    public function getItemsA(): array
    {
        return $this->itemsA;
    }

    public function addItemA(SetterIndexCacheItemA $item): void
    {
        $this->itemsA[] = $item;
    }

    public function getItemsB(): array
    {
        return $this->itemsB;
    }

    public function addItemB(SetterIndexCacheItemB $item): void
    {
        $this->itemsB[] = $item;
    }
}

final class SetterIndexCacheMethod
{
    public ?SetterIndexCacheRequest $captured = null;

    public function call(SetterIndexCacheRequest $request): array
    {
        $this->captured = $request;

        return ['ok' => true];
    }
}

/**
 * prepareParametersFromClass() indexes each DTO's setters by class name in a static cache
 * shared across all RequestHandler instances for the lifetime of the process. Two DTOs
 * that both declare a same-named setter (setValue) with different parameter types are
 * used here to catch a cache keyed incorrectly (e.g. by method name alone), and repeated
 * hydration in the same batch checks that cached ReflectionMethod entries stay reusable.
 */
final class SetterIndexCacheTest extends TestCase
{
    public function testSameMethodNameOnDifferentClassesHydratesWithCorrectType(): void
    {
        $method = new SetterIndexCacheMethod();

        $methodSpec = new MethodSpec(
            methodClass: SetterIndexCacheMethod::class,
            requestType: 'POST',
            methodName: 'setterIndexCache',
            requestMetadata: new RequestMetadata(
                request: SetterIndexCacheRequest::class,
                allParameters: [
                    ['name' => 'itemsA', 'type' => SetterIndexCacheItemA::class],
                    ['name' => 'itemsB', 'type' => SetterIndexCacheItemB::class],
                ],
                requiredParameters: [],
                requestGetters: ['itemsA' => 'getItemsA', 'itemsB' => 'getItemsB'],
                requestSetters: [],
                requestAdders: ['itemsA' => 'addItemA', 'itemsB' => 'addItemB'],
                validators: [],
            ),
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: false),
        );

        $specCollection = new MethodSpecCollection();
        $specCollection->addMethodSpec(1, 'setterIndexCache', $methodSpec);

        $handler = $this->buildHandler($specCollection, SetterIndexCacheMethod::class, $method);

        $handler->processBatch([
            'jsonrpc' => '2.0',
            'method' => 'setterIndexCache',
            'params' => [
                'itemsA' => [['value' => 1], ['value' => 2], ['value' => 3]],
                'itemsB' => [['value' => 'x'], ['value' => 'y']],
            ],
            'id' => '1',
        ], 1, 'POST');

        $this->assertNotNull($method->captured);
        $this->assertCount(3, $method->captured->getItemsA());
        $this->assertSame([1, 2, 3], array_map(static fn (SetterIndexCacheItemA $item): int => $item->getValue(), $method->captured->getItemsA()));

        $this->assertCount(2, $method->captured->getItemsB());
        $this->assertSame(['x', 'y'], array_map(static fn (SetterIndexCacheItemB $item): string => $item->getValue(), $method->captured->getItemsB()));
    }

    public function testRepeatedRequestsReuseCacheWithoutCrossContamination(): void
    {
        $methodSpec = new MethodSpec(
            methodClass: SetterIndexCacheMethod::class,
            requestType: 'POST',
            methodName: 'setterIndexCache',
            requestMetadata: new RequestMetadata(
                request: SetterIndexCacheRequest::class,
                allParameters: [['name' => 'itemsA', 'type' => SetterIndexCacheItemA::class]],
                requiredParameters: [],
                requestGetters: ['itemsA' => 'getItemsA'],
                requestSetters: [],
                requestAdders: ['itemsA' => 'addItemA'],
                validators: [],
            ),
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: false),
        );

        $specCollection = new MethodSpecCollection();
        $specCollection->addMethodSpec(1, 'setterIndexCache', $methodSpec);

        foreach ([10, 20, 30] as $expected) {
            $method = new SetterIndexCacheMethod();
            $handler = $this->buildHandler($specCollection, SetterIndexCacheMethod::class, $method);

            $handler->processBatch([
                'jsonrpc' => '2.0',
                'method' => 'setterIndexCache',
                'params' => ['itemsA' => [['value' => $expected]]],
                'id' => '1',
            ], 1, 'POST');

            $this->assertSame($expected, $method->captured->getItemsA()[0]->getValue());
        }
    }

    private function buildHandler(
        MethodSpecCollection $specCollection,
        string $methodClass,
        object $methodInstance,
    ): RequestHandler {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturn(true);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $headersPreparer = new HeadersPreparer(['*']);
        $responseService = new ResponseService($headersPreparer, new ErrorSanitizer());

        $container = $this->createMock(ServiceLocator::class);
        $container->method('get')->willReturnMap([
            [$methodClass, $methodInstance],
        ]);

        return new RequestHandler(
            $security,
            $specCollection,
            $validator,
            $headersPreparer,
            $container,
            $responseService,
            new NullJsonRpcCallLogger(),
        );
    }
}
