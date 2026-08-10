[English](testing.md) · [Русский](testing.ru.md)

# Testing

A guide to writing tests for RPC methods built on this bundle.

## Running the bundle's own tests

```bash
./vendor/bin/phpunit tests/
```

The bundle's whole suite should be green (use `--testdox` or `--colors` for legibility; the exact number of tests grows from release to release — do not rely on it as an invariant). A failure after `composer update` is a potential regression; please open an issue.

### Coverage

Install a driver:

```bash
sudo pecl install pcov
# or
sudo apt-get install php-xdebug
```

Then:

```bash
./vendor/bin/phpunit --coverage-text --coverage-html=build/coverage
```

`phpunit.xml.dist` already carries a `<source>` block for PHPUnit 10+ (the `MethodSpec/` classes and the Bundle class itself are excluded — they are either DTOs or DI glue).

## The layout of the bundle's tests

```
tests/
├── Controller/                       # integration, through ApiController + AbstractControllerTestCase
├── Core/
│   ├── Annotation/                   # unit tests of the attributes
│   ├── Request/                      # BaseRequest, JsonRpcRequest, PartialUpdateRequest
│   ├── Response/                     # JsonResponse, ErrorResponse, BaseResponse
│   └── Services/                     # RequestHandler, ResponseService, etc.
├── Command/                          # SwaggerGenerate
├── DependencyInjection/              # Configuration, Extension, CompilerPass, MethodSpec/*
├── Security/                         # security regression tests (DoS, sanitisation, CORS)
├── Swagger/                          # SwaggerSchemaBuilder, schema components
└── Fixtures/                         # test RPC methods, not executed by PHPUnit
    └── RPC/V1/
        ├── SubtractMethod.php
        ├── Subtract/SubtractRequest.php
        └── ...
```

PHPUnit excludes `tests/Fixtures` — those are ordinary classes reachable through the autoloader (composer's `autoload-dev` maps `OV\JsonRPCAPIBundle\RPC\` → `tests/Fixtures/RPC/`).

## The integration-test pattern with `AbstractControllerTestCase`

`tests/Controller/AbstractControllerTestCase.php` assembles a minimal controller stack (RequestHandler, RequestRawDataHandler, ResponseService, a mocked Security, a mocked or real ValidatorInterface, and two mocked `ServiceLocator`s — one for RPC methods, one for processors). In 4.x a mocked `Container` sat in that place; from 5.0 the bundle does not inject a container at all — see [upgrade-5.0.md](./upgrade-5.0.md), item 17.

> ⚠️ **This class is not in the installed package.** `.gitattributes` marks `/tests` as `export-ignore`, and `OV\JsonRPCAPIBundle\Tests\` is declared in `autoload-dev` — so the `tests/` directory never reaches `vendor/` and the namespace is not autoloaded. You **cannot** extend `AbstractControllerTestCase` from your own project; you will get `Class not found`. What follows shows what the harness looks like — **copy it into your project** under your own namespace. If you would rather not copy, take the [`KernelTestCase` recipe](#an-integration-test-through-kerneltestcase) instead: it needs no copying and does not depend on the bundle's internals.

> **This harness leans on internals deliberately.** Since 5.0 both the stack classes (`RequestHandler`, `RequestRawDataHandler`, `ResponseService`, `HeadersPreparer`) and the method metadata (`MethodSpec`, `RequestMetadata`, `SwaggerMetadata`) described by hand below are marked `@internal`. Their signatures may change in a 5.x minor release. Copy the harness if you want speed and full control over the stack, but be ready to adjust it on upgrade — that is the trade, not free speed. The `KernelTestCase` test below is free of this: there the container assembles both the stack and the `MethodSpec`.
>
> One point about a hand-written `MethodSpec` in particular: it must match what `CompilerPass` generates, or the test validates a configuration that does not exist in production. It is easy to forget that for a property with a default value the compiler puts `defaultValue` into `allParameters` and sets `allowsNull: true` in `validators`, and that for a collection with an adder it rewrites `type` to the **element** type. A divergence here gives a green test over a broken production.

A test needs a `MethodSpec` described by hand, passed to `executeControllerTest($payload, $methodSpec)`:

```php
namespace App\Tests\RPC;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use App\Tests\RPC\Support\AbstractControllerTestCase;   // your copy of the harness, see the warning above
use App\RPC\V1\GetProduct\Request;
use App\RPC\V1\GetProductMethod;
use Symfony\Component\HttpFoundation\JsonResponse;

final class GetProductMethodTest extends AbstractControllerTestCase
{
    public function testHappyPath(): void
    {
        $data = [
            'jsonrpc' => '2.0',
            'method'  => 'getProduct',
            'params'  => ['id' => 1],
            'id'      => 1,
        ];

        $methodSpec = new MethodSpec(
            methodClass: GetProductMethod::class,
            requestType: 'POST',
            methodName: 'getProduct',
            requestMetadata: new RequestMetadata(
                request: Request::class,
                allParameters: [['name' => 'id', 'type' => 'int']],
                requiredParameters: [['name' => 'id', 'type' => 'int']],
                requestGetters: ['id' => 'getId'],
                requestSetters: ['id' => 'setId'],
                requestAdders: [],
                validators: ['id' => ['allowsNull' => false, 'type' => 'int']],
            ),
            swaggerMetadata: new SwaggerMetadata(summary: '', description: '', ignoreInSwagger: true),
        );

        $result = $this->executeControllerTest($data, $methodSpec);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $payload = json_decode($result->getContent(), true);
        $this->assertSame(1, $payload['id']);
        $this->assertArrayHasKey('result', $payload);
    }
}
```

> ⚠️ Remember that `strict_notifications` defaults to `true` from 4.0. With no `id` in the payload there is no response. Always supply an `id` when testing RPC methods, unless your case really is about a notification.

## Unit-testing request DTOs

DTOs are ordinary PHP classes and are tested as such. If you use `PartialUpdateRequest`:

```php
use OV\JsonRPCAPIBundle\Core\Request\PartialUpdateRequest;

class UpdateUserRequest extends PartialUpdateRequest
{
    private ?int $id = null;
    private ?string $email = null;
    private ?string $bio = null;

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): void { $this->id = $id; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): void { $this->email = $email; }
    public function getBio(): ?string { return $this->bio; }
    public function setBio(?string $bio): void { $this->bio = $bio; }
}
```

A test of the `wasProvided()` semantics:

```php
public function testPartialProvidedTracking(): void
{
    $request = new UpdateUserRequest();
    $request->setEmail('a@b.com');
    $request->markProvided('email');

    $this->assertTrue($request->wasProvided('email'));
    $this->assertFalse($request->wasProvided('bio'));
}
```

The bundle calls `markProvided()` automatically in `RequestHandler::hydrateRequest` for DTOs implementing `PartialRequestInterface`. In unit tests you call it yourself.

## Testing processors

Pre- and post-processors are methods on the RPC method class itself. To check the order of calls:

```php
public function testProcessorsRunInOrder(): void
{
    $log = [];
    $method = new class implements ApiMethodInterface, PreProcessorInterface, PostProcessorInterface {
        public array $log;
        public function getPreProcessors(): array { return [static::class => ['logPre']]; }
        public function getPostProcessors(): array { return [static::class => ['logPost']]; }
        public function logPre(string $cls, ?object $req): void { $this->log[] = 'pre'; }
        public function logPost(string $cls, ?object $req, ?OvResponseInterface $resp): void { $this->log[] = 'post'; }
        public function call($r): mixed { $this->log[] = 'call'; return ['ok' => true]; }
    };

    // ... run it through RequestHandler ...

    $this->assertSame(['pre', 'call', 'post'], $method->log);
}
```

## An integration test through `KernelTestCase`

This route asks nothing of the bundle beyond its public contract: the container assembles both the stack and the `MethodSpec`, there is nothing to copy, and changes to internals in minor releases do not touch it. It is also what you need when the RPC method reaches a database through Doctrine:

```php
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CreateUserMethodTest extends KernelTestCase
{
    public function testCreatesUser(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $method = $container->get(CreateUserMethod::class);

        $request = new CreateUserRequest();
        $request->setEmail('a@b.com');

        $response = $method->call($request);

        $this->assertTrue($response->isSuccess());
        // assert against the database...
    }
}
```

Combine it with `dama/doctrine-test-bundle` or with transactions to isolate the tests from one another.

## The security regression tests as an example

The bundle's `tests/Security/` directory holds an example for each DoS vector:

- `PayloadLimitTest.php` — payload size, JSON depth.
- `BatchSizeLimitTest.php` — an oversized batch.
- `DtoHydrationLimitsTest.php` — DTO recursion depth, setter visibility.
- `ArrayParamLimitTest.php` — exceeding `max_array_param_size`.
- `ErrorSanitizationTest.php` — sanitisation and logging.
- `CorsMultiOriginTest.php` — origin matching.
- `SwaggerGenerateSecurityTest.php` — path containment in the command.

If you extend the bundle with limits of your own, write regression tests along the same lines.

## CI

The bundle itself is built by `.github/workflows/ci.yml`: a matrix of PHP 8.2/8.3/8.4/8.5 against Symfony `^6.4`/`^7.0`/`^8.0` — ten combinations, excluding Symfony 8 below PHP 8.4, where the combination cannot exist — plus a job on the lowest permitted dependencies, a coverage gate (90% line coverage minimum), PHPStan level 9, PHP-CS-Fixer, `composer validate --strict` and `composer audit` on a weekly schedule (so a new advisory surfaces even with no commits), and a non-blocking Symfony 9 canary. Read the file in full as a reference if you are setting up CI for a project that uses the bundle.

A minimal workflow for your own project, just to run tests that use the bundle:

```yaml
name: tests
on: [push, pull_request]
jobs:
    phpunit:
        runs-on: ubuntu-latest
        strategy:
            matrix:
                php: [8.2, 8.3, 8.4, 8.5]
        steps:
            - uses: actions/checkout@v7
            - uses: shivammathur/setup-php@v2
              with:
                  php-version: ${{ matrix.php }}
                  coverage: pcov
            - run: composer install --prefer-dist --no-progress
            - run: ./vendor/bin/phpunit --coverage-text
```

## Related

- [security_hardening.md](./security_hardening.md) — which limits are worth testing
- [partial_updates.md](./partial_updates.md) — the semantics of wasProvided
- [batch.md](./batch.md) — batch cases
