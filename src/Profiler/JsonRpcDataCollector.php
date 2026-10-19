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
        $calls = $this->callLogger->getCallsForRequest($request);
        $this->data = [
            'methods' => $calls === [] ? [] : $this->normaliseMethods($this->methodSpecs->getAllMethods()),
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
                $required = array_column($method->getRequiredParameters(), null, 'name');
                $parameters = [];
                foreach ($method->getAllParameters() as $parameter) {
                    $parameterName = $parameter['name'];
                    $parameters[] = [
                        'name' => $parameterName,
                        'type' => $parameter['type'],
                        'required' => isset($required[$parameterName]),
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
                ];
            }
        }

        return $normalised;
    }

    /** @param list<array<string, mixed>> $calls */
    private function groupCalls(array $calls): array
    {
        $groups = [];
        foreach ($calls as $index => $call) {
            $batchId = is_int($call['batchId']) ? $call['batchId'] : null;
            $key = $batchId === null ? 'call-' . $index : 'batch-' . $batchId;
            $groups[$key] ??= ['batch' => $batchId !== null, 'calls' => []];
            unset($call['batchId']);
            $groups[$key]['calls'][] = $call;
        }

        return array_values($groups);
    }
}
