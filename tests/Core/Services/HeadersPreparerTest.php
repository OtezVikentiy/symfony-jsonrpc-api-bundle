<?php

namespace OV\JsonRPCAPIBundle\Tests\Core\Services;

use OV\JsonRPCAPIBundle\Core\Services\HeadersPreparer;
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

    public function testOriginNotInWhitelistEmitsNoCorsHeaderInStrictMode(): void
    {
        $preparer = new HeadersPreparer(
            ['https://a.com', 'https://b.com'],
            $this->stackWithOrigin('https://evil.com'),
        );

        $headers = $preparer->prepareHeaders();

        $this->assertSame([], $headers);
    }

    public function testWildcardOrigin(): void
    {
        $preparer = new HeadersPreparer(['*']);
        $headers = $preparer->prepareHeaders();

        $this->assertSame('*', $headers['Access-Control-Allow-Origin']);
        $this->assertArrayNotHasKey('Vary', $headers);
    }

    public function testEmptyOriginList(): void
    {
        $preparer = new HeadersPreparer([]);
        $headers = $preparer->prepareHeaders();

        $this->assertSame('', $headers['Access-Control-Allow-Origin']);
    }

    public function testLegacyModeFallsBackToCommaJoined(): void
    {
        $preparer = new HeadersPreparer(
            ['https://a.com', 'https://b.com'],
            $this->stackWithOrigin(null),
            corsStrict: false,
        );

        $headers = $preparer->prepareHeaders();

        $this->assertSame('https://a.com, https://b.com', $headers['Access-Control-Allow-Origin']);
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
