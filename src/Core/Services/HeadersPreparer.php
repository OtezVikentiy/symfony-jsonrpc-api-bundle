<?php

namespace OV\JsonRPCAPIBundle\Core\Services;

use Symfony\Component\HttpFoundation\RequestStack;

final readonly class HeadersPreparer
{
    public function __construct(
        private array $accessControlAllowOriginList,
        private ?RequestStack $requestStack = null,
        private bool $corsStrict = true,
    ) {
    }

    /**
     * Build CORS response headers for the current request.
     *
     * Behaviour:
     * - Wildcard `*` in the whitelist → always returns `Access-Control-Allow-Origin: *`.
     * - One whitelisted origin matches the request `Origin` header → returns that exact
     *   origin plus `Vary: Origin` so caches stay correct.
     * - No match and `cors_strict=true` → no CORS header is emitted at all.
     * - No match and `cors_strict=false` → falls back to legacy behaviour: comma-joined list.
     *
     * @return array<string,string>
     */
    public function prepareHeaders(): array
    {
        if ($this->accessControlAllowOriginList === []) {
            return ['Access-Control-Allow-Origin' => ''];
        }

        if (in_array('*', $this->accessControlAllowOriginList, true)) {
            return ['Access-Control-Allow-Origin' => '*'];
        }

        $origin = $this->currentOrigin();

        if ($origin !== null && in_array($origin, $this->accessControlAllowOriginList, true)) {
            return [
                'Access-Control-Allow-Origin' => $origin,
                'Vary' => 'Origin',
            ];
        }

        if ($this->corsStrict) {
            return [];
        }

        return ['Access-Control-Allow-Origin' => implode(', ', $this->accessControlAllowOriginList)];
    }

    private function currentOrigin(): ?string
    {
        $request = $this->requestStack?->getCurrentRequest();
        if ($request === null) {
            return null;
        }

        $origin = $request->headers->get('Origin');
        if (!is_string($origin) || $origin === '') {
            return null;
        }

        return $origin;
    }
}
