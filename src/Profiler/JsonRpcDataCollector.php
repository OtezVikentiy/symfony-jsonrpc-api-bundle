<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Profiler;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpecCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Throwable;

/** @internal */
final class JsonRpcDataCollector extends DataCollector
{
    public function __construct(
        private readonly TraceableJsonRpcCallLogger $callLogger,
        private readonly MethodSpecCollection $methodSpecs,
    ) {
    }

    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        $calls = $this->callLogger->getCalls();
        $this->data = [
            'methods' => $this->normaliseMethods($this->methodSpecs->getAllMethods()),
            'callGroups' => $this->groupCalls($calls),
            'callCount' => count($calls),
        ];
    }

    public function getName(): string
    {
        return 'ov_json_rpc_api';
    }

    /** @return list<array<string, mixed>> */
    public function getMethods(): array
    {
        return $this->data['methods'] ?? [];
    }

    /** @return list<array<string, mixed>> */
    public function getCallGroups(): array
    {
        return $this->data['callGroups'] ?? [];
    }

    public function getCallCount(): int
    {
        return $this->data['callCount'] ?? 0;
    }

    public function getMethodCount(): int
    {
        return count($this->getMethods());
    }

    /**
     * @param array<int, array<string, MethodSpec>> $methods
     *
     * @return list<array<string, mixed>>
     */
    private function normaliseMethods(array $methods): array
    {
        $normalised = [];
        ksort($methods);

        foreach ($methods as $version => $versionMethods) {
            ksort($versionMethods);
            foreach ($versionMethods as $name => $method) {
                $validators = $method->getValidators();
                $parameters = [];
                foreach ($method->getAllParameters() as $parameter) {
                    $parameterName = $parameter['name'];
                    $parameters[] = [
                        'name' => $parameterName,
                        'type' => $parameter['type'],
                        'required' => !($validators[$parameterName]['allowsNull'] ?? false),
                    ];
                }

                $normalised[] = [
                    'version' => $version,
                    'name' => $name,
                    'class' => $method->getMethodClass(),
                    'request' => $method->getRequest(),
                    'parameters' => $parameters,
                    'summary' => $method->getSummary(),
                    'description' => $method->getDescription(),
                    'tags' => $method->getTags() ?? [],
                    'group' => $method->getGroup(),
                    'roles' => $method->getRoles(),
                    'plainResponse' => $method->isPlainResponse(),
                    'ignoredInSwagger' => $method->isIgnoreInSwagger(),
                ];
            }
        }

        return $normalised;
    }

    /**
     * One HTTP request can carry either one JSON-RPC call or one batch. The logger is reset between
     * kernel requests, so more than one recorded call necessarily belongs to the same batch.
     *
     * @param list<array<string, mixed>> $calls
     *
     * @return list<array<string, mixed>>
     */
    private function groupCalls(array $calls): array
    {
        if ($calls === []) {
            return [];
        }

        $firstStart = null;
        $lastFinish = null;
        foreach ($calls as $call) {
            if (is_float($call['startedAt'])) {
                $firstStart = $firstStart === null ? $call['startedAt'] : min($firstStart, $call['startedAt']);
            }
            if (is_float($call['finishedAt'])) {
                $lastFinish = $lastFinish === null ? $call['finishedAt'] : max($lastFinish, $call['finishedAt']);
            }
        }
        $durationMs = $firstStart !== null && $lastFinish !== null
            ? ($lastFinish - $firstStart) * 1000
            : null;

        return [[
            'batch' => count($calls) > 1,
            'calls' => $calls,
            'durationMs' => $durationMs,
        ]];
    }
}
