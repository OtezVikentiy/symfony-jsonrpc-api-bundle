<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Core\Services;

use Symfony\Component\HttpFoundation\RequestStack;

final readonly class HeadersPreparer
{
    private const HEADER_ALLOW_ORIGIN = 'Access-Control-Allow-Origin';
    private const HEADER_VARY = 'Vary';
    private const HEADER_ORIGIN = 'Origin';
    private const HEADER_ALLOW_METHODS = 'Access-Control-Allow-Methods';
    private const HEADER_ALLOW_HEADERS = 'Access-Control-Allow-Headers';
    private const HEADER_MAX_AGE = 'Access-Control-Max-Age';
    private const DEFAULT_ALLOWED_HEADERS = ['Content-Type'];
    private const METHODS_SEPARATOR = ', ';
    private const PREFLIGHT_MAX_AGE_SECONDS = 86400;

    public function __construct(
        private array $accessControlAllowOriginList,
        private ?RequestStack $requestStack = null,
        private array $allowedHeaders = self::DEFAULT_ALLOWED_HEADERS,
    ) {
    }

    /**
     * Build CORS response headers for the current request.
     *
     * Behaviour:
     * - Wildcard `*` in the whitelist → always returns `Access-Control-Allow-Origin: *`.
     * - One whitelisted origin matches the request `Origin` header → returns that exact
     *   origin plus `Vary: Origin` so caches stay correct.
     * - No match, no `Origin` header, or an empty whitelist → no CORS header is emitted.
     *
     * @return array<string,string>
     */
    public function prepareHeaders(): array
    {
        if ($this->accessControlAllowOriginList === []) {
            return [];
        }

        if (in_array('*', $this->accessControlAllowOriginList, true)) {
            return [self::HEADER_ALLOW_ORIGIN => '*'];
        }

        $origin = $this->currentOrigin();

        if ($origin !== null && in_array($origin, $this->accessControlAllowOriginList, true)) {
            return [
                self::HEADER_ALLOW_ORIGIN => $origin,
                self::HEADER_VARY => self::HEADER_ORIGIN,
            ];
        }

        return [];
    }

    /**
     * Build CORS response headers for an `OPTIONS` preflight request.
     *
     * Origin matching follows the same rules as {@see self::prepareHeaders()}. The
     * preflight-specific headers (allowed methods, allowed headers, cache lifetime) are
     * always present, independent of whether the origin matched. The allowed-headers list
     * is the configured whitelist verbatim — it never reflects the request's
     * `Access-Control-Request-Headers` back, since that would turn the check into a
     * formality that grants whatever the client asks for.
     *
     * @param string[] $allowedMethods methods actually declared on the route being preflighted
     *
     * @return array<string,string>
     */
    public function preparePreflightHeaders(array $allowedMethods): array
    {
        return array_merge(
            $this->prepareHeaders(),
            [
                self::HEADER_ALLOW_METHODS => implode(self::METHODS_SEPARATOR, $allowedMethods),
                self::HEADER_ALLOW_HEADERS => implode(self::METHODS_SEPARATOR, $this->allowedHeaders),
                self::HEADER_MAX_AGE => (string) self::PREFLIGHT_MAX_AGE_SECONDS,
            ],
        );
    }

    private function currentOrigin(): ?string
    {
        $request = $this->requestStack?->getCurrentRequest();
        if ($request === null) {
            return null;
        }

        $origin = $request->headers->get(self::HEADER_ORIGIN);
        if (!is_string($origin) || $origin === '') {
            return null;
        }

        return $origin;
    }
}
