<?php

namespace OV\JsonRPCAPIBundle\Tests\Security;

use OV\JsonRPCAPIBundle\Core\Services\HeadersPreparer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CorsMultiOriginTest extends TestCase
{
    public function testWhitelistedOriginIsEchoedBack(): void
    {
        $preparer = new HeadersPreparer(
            ['https://api.example.com', 'https://admin.example.com'],
            $this->stack('https://admin.example.com'),
        );

        $headers = $preparer->prepareHeaders();

        $this->assertSame('https://admin.example.com', $headers['Access-Control-Allow-Origin']);
        $this->assertSame('Origin', $headers['Vary']);
    }

    public function testForeignOriginGetsNoHeader(): void
    {
        $preparer = new HeadersPreparer(
            ['https://api.example.com'],
            $this->stack('https://evil.com'),
        );

        $this->assertSame([], $preparer->prepareHeaders());
    }

    public function testMissingOriginHeaderEmitsNothingWhenStrict(): void
    {
        $preparer = new HeadersPreparer(
            ['https://api.example.com'],
            $this->stack(null),
        );

        $this->assertSame([], $preparer->prepareHeaders());
    }

    public function testWildcardOverridesMatching(): void
    {
        $preparer = new HeadersPreparer(['*'], $this->stack('https://evil.com'));

        $this->assertSame(['Access-Control-Allow-Origin' => '*'], $preparer->prepareHeaders());
    }

    private function stack(?string $origin): RequestStack
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
