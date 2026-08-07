<?php

namespace OV\JsonRPCAPIBundle\Tests\Core\Services;

use OV\JsonRPCAPIBundle\Core\Services\HeadersPreparer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class HeadersPreparerTest extends TestCase
{
    public function testSingleOriginMatchesRequest(): void
    {
        $preparer = new HeadersPreparer(
            ['https://example.com'],
            $this->stackWithOrigin('https://example.com'),
        );

        $headers = $preparer->prepareHeaders();

        $this->assertSame('https://example.com', $headers['Access-Control-Allow-Origin']);
        $this->assertSame('Origin', $headers['Vary']);
    }

    public function testMultipleOriginsPickWhichever(): void
    {
        $preparer = new HeadersPreparer(
            ['https://a.com', 'https://b.com'],
            $this->stackWithOrigin('https://b.com'),
        );

        $headers = $preparer->prepareHeaders();

        $this->assertSame('https://b.com', $headers['Access-Control-Allow-Origin']);
        $this->assertSame('Origin', $headers['Vary']);
    }

    /**
     * The whitelist is matched for equality, and these are the origins that a looser comparison
     * would wave through. testOriginNotInWhitelistEmitsNoCorsHeader() cannot catch a regression to
     * substring or prefix matching, because the origin it sends shares nothing with the allowed one.
     */
    #[DataProvider('originsThatOnlyLookAllowed')]
    public function testOriginResemblingAWhitelistedOneIsRejected(string $origin): void
    {
        $preparer = new HeadersPreparer(
            ['https://app.example.com'],
            $this->stackWithOrigin($origin),
        );

        $this->assertArrayNotHasKey(
            'Access-Control-Allow-Origin',
            $preparer->prepareHeaders(),
            sprintf('%s must not receive a CORS header', $origin),
        );
    }

    public static function originsThatOnlyLookAllowed(): array
    {
        return [
            'attacker-controlled suffix' => ['https://app.example.com.evil.io'],
            'whitelisted origin as a path' => ['https://evil.io/https://app.example.com'],
            'whitelisted origin as a prefix' => ['https://app.example.competitor.io'],
            'explicit port' => ['https://app.example.com:8443'],
            'downgraded scheme' => ['http://app.example.com'],
            'extra subdomain' => ['https://sub.app.example.com'],
            'trailing slash' => ['https://app.example.com/'],
            'uppercased host' => ['https://APP.EXAMPLE.COM'],
            'two origins in one header' => ['https://app.example.com https://evil.io'],
            'literal null origin' => ['null'],
        ];
    }

    public function testOriginNotInWhitelistEmitsNoCorsHeader(): void
    {
        $preparer = new HeadersPreparer(
            ['https://a.com', 'https://b.com'],
            $this->stackWithOrigin('https://evil.com'),
        );

        $headers = $preparer->prepareHeaders();

        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $headers);
        $this->assertSame('Origin', $headers['Vary'], 'a denial depends on Origin just as a grant does, so caches must be told');
    }

    public function testWildcardOrigin(): void
    {
        $preparer = new HeadersPreparer(['*']);
        $headers = $preparer->prepareHeaders();

        $this->assertSame('*', $headers['Access-Control-Allow-Origin']);
        $this->assertArrayNotHasKey('Vary', $headers);
    }

    public function testEmptyOriginListEmitsNoHeaderAtAll(): void
    {
        $preparer = new HeadersPreparer([]);
        $headers = $preparer->prepareHeaders();

        $this->assertSame([], $headers);
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $headers);
    }

    public function testPreflightHeadersIncludeMethodsHeadersAndMaxAge(): void
    {
        $preparer = new HeadersPreparer(
            ['https://example.com'],
            $this->stackWithOrigin('https://example.com'),
        );

        $headers = $preparer->preparePreflightHeaders(['POST', 'GET', 'PUT', 'PATCH', 'DELETE']);

        $this->assertSame('https://example.com', $headers['Access-Control-Allow-Origin']);
        $this->assertSame('Origin', $headers['Vary']);
        $this->assertSame('POST, GET, PUT, PATCH, DELETE', $headers['Access-Control-Allow-Methods']);
        $this->assertArrayHasKey('Access-Control-Allow-Headers', $headers);
        $this->assertArrayHasKey('Access-Control-Max-Age', $headers);
        $this->assertIsNumeric($headers['Access-Control-Max-Age']);
    }

    public function testPreflightHeadersOmitAllowOriginWhenOriginDoesNotMatch(): void
    {
        $preparer = new HeadersPreparer(
            ['https://example.com'],
            $this->stackWithOrigin('https://evil.com'),
        );

        $headers = $preparer->preparePreflightHeaders(['POST']);

        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $headers);
        $this->assertSame('POST', $headers['Access-Control-Allow-Methods']);
        $this->assertArrayHasKey('Access-Control-Allow-Headers', $headers);
        $this->assertArrayHasKey('Access-Control-Max-Age', $headers);
    }

    public function testPreflightAllowedHeadersDefaultsToContentTypeOnly(): void
    {
        $preparer = new HeadersPreparer(['*']);

        $headers = $preparer->preparePreflightHeaders(['POST']);

        $this->assertSame('Content-Type', $headers['Access-Control-Allow-Headers']);
    }

    public function testPreflightAllowedHeadersUsesConfiguredList(): void
    {
        $preparer = new HeadersPreparer(['*'], null, ['Content-Type', 'X-AUTH-TOKEN']);

        $headers = $preparer->preparePreflightHeaders(['POST']);

        $this->assertSame('Content-Type, X-AUTH-TOKEN', $headers['Access-Control-Allow-Headers']);
    }

    public function testPreflightAllowedHeadersDoesNotReflectRequestedHeaders(): void
    {
        $request = new Request();
        $request->headers->set('Access-Control-Request-Headers', 'X-Whatever-The-Client-Wants');
        $stack = new RequestStack();
        $stack->push($request);

        $preparer = new HeadersPreparer(['*'], $stack);

        $headers = $preparer->preparePreflightHeaders(['POST']);

        $this->assertSame('Content-Type', $headers['Access-Control-Allow-Headers']);
    }

    private function stackWithOrigin(?string $origin): RequestStack
    {
        $request = new Request();
        if ($origin !== null) {
            $request->headers->set('Origin', $origin);
        }
        $stack = new RequestStack();
        $stack->push($request);

        return $stack;
    }
}
