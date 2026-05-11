# Testing

Гайд по написанию тестов для RPC-методов, построенных на этом бандле.

## Запуск тестов бандла

```bash
./vendor/bin/phpunit tests/
```

Все 327+ тестов должны быть зелёными. Если упало после `composer update` — это потенциальный регресс, заведите issue.

### Coverage

Установите драйвер:

```bash
sudo pecl install pcov
# или
sudo apt-get install php-xdebug
```

Затем:

```bash
./vendor/bin/phpunit --coverage-text --coverage-html=build/coverage
```

В `phpunit.xml.dist` уже описан `<source>`-блок для PHPUnit 10+ (исключены `MethodSpec/`-классы и сам Bundle-класс — они или DTO, или DI-glue).

## Структура тестов бандла

```
tests/
├── Controller/                       # интеграционные через ApiController + AbstractControllerTestCase
├── Core/
│   ├── Annotation/                   # юнит-тесты атрибутов
│   ├── Request/                      # BaseRequest, JsonRpcRequest, PartialUpdateRequest
│   ├── Response/                     # JsonResponse, ErrorResponse, BaseResponse
│   └── Services/                     # RequestHandler, ResponseService, etc.
├── Command/                          # SwaggerGenerate
├── DependencyInjection/              # Configuration, Extension, CompilerPass, MethodSpec/*
├── Security/                         # security regression-тесты (DoS, sanitization, CORS)
├── Swagger/                          # SwaggerSchemaBuilder, schema components
└── Fixtures/                         # тестовые RPC-методы, не исполняются PHPUnit'ом
    └── RPC/V1/
        ├── SubtractMethod.php
        ├── Subtract/SubtractRequest.php
        └── ...
```

PHPUnit исключает `tests/Fixtures` — это обычные классы, доступные через autoload (composer autoload-dev маппит `OV\JsonRPCAPIBundle\RPC\` → `tests/Fixtures/RPC/`).

## Паттерн интеграционного теста через `AbstractControllerTestCase`

`tests/Controller/AbstractControllerTestCase.php` собирает минимальный controller-стек (RequestHandler, RequestRawDataHandler, ResponseService, замоканный Security, замоканный ValidatorInterface или реальный, замоканный Container). Для теста нужно описать `MethodSpec` руками и передать `executeControllerTest($payload, $methodSpec)`:

```php
namespace App\Tests\RPC;

use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\RequestMetadata;
use OV\JsonRPCAPIBundle\DependencyInjection\MethodSpec\SwaggerMetadata;
use OV\JsonRPCAPIBundle\Tests\Controller\AbstractControllerTestCase;
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

> ⚠️ Помните: с 4.0 default `strict_notifications: true`. Если в payload нет `id`, ответа не будет. Для тестов RPC-методов всегда указывайте `id`, либо ваш кейс действительно про notification.

## Юнит-тестирование Request DTO

DTO — обычные PHP-классы, тестируются как обычно. Если используете `PartialUpdateRequest`:

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

Тест семантики `wasProvided()`:

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

`markProvided()` бандл вызывает автоматически в `RequestHandler::hydrateRequest` для DTO, реализующих `PartialRequestInterface`. В юнит-тестах вы вызываете руками.

## Тестирование процессоров

Pre/post-процессоры — методы на классе самого RPC-метода. Чтобы проверить порядок вызовов:

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

    // ... запустить через RequestHandler ...

    $this->assertSame(['pre', 'call', 'post'], $method->log);
}
```

## Тестирование с реальной БД

Если RPC-метод обращается к БД через Doctrine — используйте `KernelTestCase` для интеграционного теста с реальным контейнером:

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
        // ассерт в БД...
    }
}
```

Совмещайте с `dama/doctrine-test-bundle` или транзакциями для изоляции между тестами.

## Security regression-тесты как пример

Папка `tests/Security/` бандла содержит примеры тестов на каждый DoS-вектор:

- `PayloadLimitTest.php` — payload size, JSON depth.
- `BatchSizeLimitTest.php` — большой batch.
- `DtoHydrationLimitsTest.php` — глубина рекурсии DTO, видимость сеттера.
- `ArrayParamLimitTest.php` — превышение `max_array_param_size`.
- `ErrorSanitizationTest.php` — sanitization + логирование.
- `CorsMultiOriginTest.php` — origin matching.
- `SwaggerGenerateSecurityTest.php` — path containment команды.

Если расширяете бандл с собственными лимитами — пишите аналогичные regression-тесты.

## CI

Минимальный workflow для GitHub Actions:

```yaml
name: tests
on: [push, pull_request]
jobs:
    phpunit:
        runs-on: ubuntu-latest
        strategy:
            matrix:
                php: [8.2, 8.3, 8.4]
        steps:
            - uses: actions/checkout@v4
            - uses: shivammathur/setup-php@v2
              with:
                  php-version: ${{ matrix.php }}
                  coverage: pcov
            - run: composer install --prefer-dist --no-progress
            - run: ./vendor/bin/phpunit --coverage-text
```

## Связанное

- [security_hardening.md](./security_hardening.md) — какие лимиты тестировать
- [partial_updates.md](./partial_updates.md) — семантика wasProvided
- [batch.md](./batch.md) — батч-кейсы
