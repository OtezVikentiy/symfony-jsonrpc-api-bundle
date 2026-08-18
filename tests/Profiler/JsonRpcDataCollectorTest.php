<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Profiler;

use OV\JsonRPCAPIBundle\Core\Logging\NullJsonRpcCallLogger;
use OV\JsonRPCAPIBundle\Core\Logging\SensitiveDataMasker;
use OV\JsonRPCAPIBundle\Core\Logging\UuidContextIdGenerator;
use OV\JsonRPCAPIBundle\Core\Response\JsonResponse;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use OV\JsonRPCAPIBundle\Profiler\JsonRpcDataCollector;
use OV\JsonRPCAPIBundle\Profiler\TraceableJsonRpcCallLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class JsonRpcDataCollectorTest extends TestCase
{
    public function testItGroupsBatchChildrenAndPublishesCompiledRegistryMetadata(): void
    {
        $traceable = new TraceableJsonRpcCallLogger(
            new NullJsonRpcCallLogger(),
            new SensitiveDataMasker([], '***', new NullLogger()),
            new UuidContextIdGenerator(),
        );
        $first = $traceable->logRequest(['method' => 'task.get', 'params' => ['id' => 1], 'id' => 1]);
        $traceable->logResponse($first, new JsonResponse(['jsonrpc' => '2.0', 'result' => ['id' => 1], 'id' => 1]));
        $second = $traceable->logRequest(['method' => 'task.get', 'params' => ['id' => 2], 'id' => 2]);
        $traceable->logResponse($second, new JsonResponse(['jsonrpc' => '2.0', 'result' => ['id' => 2], 'id' => 2]));

        $methods = new MethodSpecCollection();
        $methods->addMethodSpec(1, 'task.get', new MethodSpec(
            methodClass: ProfilerFixtureMethod::class,
            requestType: 'POST',
            methodName: 'task.get',
            requestMetadata: new RequestMetadata(
                request: ProfilerFixtureRequest::class,
                allParameters: [
                    ['name' => 'id', 'type' => 'int'],
                    ['name' => 'locale', 'type' => 'string', 'defaultValue' => null],
                ],
                requiredParameters: [['name' => 'id', 'type' => 'int']],
                requestGetters: ['id' => 'getId', 'locale' => 'getLocale'],
                requestSetters: ['id' => 'setId', 'locale' => 'setLocale'],
                requestAdders: [],
                validators: [
                    'id' => ['type' => 'int', 'allowsNull' => false],
                    'locale' => ['type' => 'string', 'allowsNull' => true],
                ],
            ),
            swaggerMetadata: new SwaggerMetadata(
                summary: 'Get a task',
                description: 'Returns one task.',
                ignoreInSwagger: false,
                tags: ['Tasks'],
                group: 'read',
            ),
            roles: ['ROLE_USER'],
        ));

        $collector = new JsonRpcDataCollector($traceable, $methods);
        $collector->collect(Request::create('/api/v1', 'POST'), new Response());

        self::assertSame(2, $collector->getCallCount());
        self::assertCount(1, $collector->getCallGroups());
        self::assertTrue($collector->getCallGroups()[0]['batch']);
        self::assertCount(2, $collector->getCallGroups()[0]['calls']);
        self::assertSame(1, $collector->getMethodCount());
        self::assertSame('task.get', $collector->getMethods()[0]['name']);
        self::assertSame('Get a task', $collector->getMethods()[0]['summary']);
        self::assertTrue($collector->getMethods()[0]['parameters'][0]['required']);
        self::assertFalse($collector->getMethods()[0]['parameters'][1]['required']);
    }
}

final class ProfilerFixtureRequest
{
    public function getId(): int { return 1; }
    public function setId(int $id): void {}
    public function getLocale(): ?string { return null; }
    public function setLocale(?string $locale): void {}
}

final class ProfilerFixtureMethod
{
    public function call(ProfilerFixtureRequest $request): array { return []; }
}
