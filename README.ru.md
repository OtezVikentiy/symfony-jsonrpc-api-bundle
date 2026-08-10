# OtezVikentiy Symfony JSON-RPC API Bundle

[English](README.md) · [Русский](README.ru.md)

[![CI](https://github.com/OtezVikentiy/symfony-jsonrpc-api-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/OtezVikentiy/symfony-jsonrpc-api-bundle/actions/workflows/ci.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/otezvikentiy/json-rpc-api.svg)](https://packagist.org/packages/otezvikentiy/json-rpc-api)
[![PHP Version](https://img.shields.io/badge/php-8.2%20--%208.5-8892BF.svg)](https://php.net/)
[![Symfony Version](https://img.shields.io/badge/symfony-%5E6.4%20%7C%7C%20%5E7.0%20%7C%7C%20%5E8.0-000000.svg)](https://symfony.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Coverage](https://img.shields.io/badge/coverage-99%25-brightgreen.svg)](https://github.com/OtezVikentiy/symfony-jsonrpc-api-bundle/actions/workflows/ci.yml)

Symfony-бандл для быстрого и удобного создания JSON-RPC 2.0 API приложений.

GitHub: https://github.com/OtezVikentiy/symfony-jsonrpc-api-bundle

---

## Принципы дизайна

- **Строгое соответствие JSON-RPC 2.0.** Запросы, батчи, нотификации, объекты ошибок и семантика `id` следуют [спецификации](https://www.jsonrpc.org/specification) дословно, и это поведение зафиксировано тестами.
- **Атрибуты и DTO вместо конфигурации.** Метод API — один класс с атрибутом `#[JsonRPCAPI]`; запрос и ответ — обычные PHP-объекты с типизированными свойствами. Никакой маршрутизации на каждый метод и никаких схем, которые надо синхронизировать с кодом.
- **Безопасные значения по умолчанию.** Лимиты на размер и вложенность payload, санитизация ошибок, CORS-whitelist и маскирование логов работают из коробки; каждый осознанный компромисс описан в [SECURITY.md](./SECURITY.ru.md).
- **Документация из того же источника, что и код.** OpenAPI/Swagger генерируется из тех же атрибутов и DTO, которые использует рантайм, поэтому не может разойтись с фактическим поведением.
- **Минимум зависимостей.** Только компоненты `symfony/*` и `psr/log` — ничего лишнего в ваш проект не приезжает.
- **Каждая заявленная комбинация версий проверяется.** CI гоняет полную матрицу PHP 8.2–8.5 на Symfony 6.4, 7 и 8, плюс сборку на минимальных версиях зависимостей, порог покрытия и порог мутационного тестирования.

---

## Возможности

- Полная совместимость со спецификацией [JSON-RPC 2.0](https://www.jsonrpc.org/specification)
- Конфигурация методов через PHP 8 атрибуты (`#[JsonRPCAPI(...)]`)
- Поддержка HTTP-методов: POST, GET, PUT, PATCH, DELETE
- Версионирование API (`/api/v1`, `/api/v2`, ...)
- Автоматическая генерация OpenAPI/Swagger документации
- Pre- и Post-процессоры (middleware)
- Пакетные запросы (batch requests)
- Встроенная валидация запросов
- Ролевой контроль доступа через Symfony Security
- Поддержка бинарных ответов (изображения, документы)

---

## Требования

- PHP 8.2 – 8.5
- Symfony ^6.4 || ^7.0 || ^8.0

---

## Установка

```bash
composer require otezvikentiy/json-rpc-api
```

Включите бандл (если не используется Symfony Flex):

```php
// config/bundles.php
return [
    // ...
    OV\JsonRPCAPIBundle\OVJsonRPCAPIBundle::class => ['all' => true],
];
```

Создайте конфигурационные файлы:

```yaml
# config/routes/ov_json_rpc_api.yaml
ov_json_rpc_api:
    resource: '@OVJsonRPCAPIBundle/config/routes/routes.yaml'
```

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    access_control_allow_origin_list:
        - '*'
    swagger:
        api_v1:
            api_version: '1'
            base_path: '%env(string:OV_JSON_RPC_API_BASE_URL)%'
            base_path_description: 'Production server'
            test_path: '%env(string:OV_JSON_RPC_API_TEST_URL)%'
            test_path_description: 'Sandbox server'
            auth_token_name: 'X-AUTH-TOKEN'
            auth_token_test_value: '%env(string:OV_JSON_RPC_API_AUTH_TOKEN)%'
            info:
                title: 'My API'
                description: 'JSON-RPC 2.0 API'
                terms_of_service_url: 'https://example.com/tos'
                contact:
                    name: 'Support'
                    url: 'https://example.com'
                    email: 'support@example.com'
                license: 'MIT'
                licenseUrl: 'https://opensource.org/licenses/MIT'
```

```dotenv
# .env
OV_JSON_RPC_API_SWAGGER_PATH=public/openapi/
OV_JSON_RPC_API_BASE_URL=http://localhost
OV_JSON_RPC_API_TEST_URL=http://localhost
OV_JSON_RPC_API_AUTH_TOKEN=your_test_token_here
```

Подробная инструкция: [docs/installation.md](./docs/installation.ru.md)

---

## Быстрый старт

### 1. Создайте Request

```php
// src/RPC/V1/GetProduct/Request.php
namespace App\RPC\V1\GetProduct;

class Request
{
    private int $id;
    private string $title;

    public function __construct(int $id)
    {
        $this->id = $id;
    }

    public function getId(): int { return $this->id; }
    public function setId(int $id): void { $this->id = $id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): void { $this->title = $title; }
}
```

### 2. Создайте Response

```php
// src/RPC/V1/GetProduct/Response.php
namespace App\RPC\V1\GetProduct;

class Response
{
    private bool $success;
    private string $title;
    private int $price;

    public function __construct(bool $success = true)
    {
        $this->success = $success;
    }

    public function isSuccess(): bool { return $this->success; }
    public function setSuccess(bool $success): void { $this->success = $success; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): void { $this->title = $title; }
    public function getPrice(): int { return $this->price; }
    public function setPrice(int $price): void { $this->price = $price; }
}
```

### 3. Создайте метод API

```php
// src/RPC/V1/GetProductMethod.php
namespace App\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\Core\ApiMethodInterface;
use App\RPC\V1\GetProduct\Request;
use App\RPC\V1\GetProduct\Response;

#[JsonRPCAPI(methodName: 'getProduct', type: 'POST')]
class GetProductMethod implements ApiMethodInterface
{
    public function call(Request $request): Response
    {
        $response = new Response();
        $response->setTitle('Iphone 15');
        $response->setPrice(2000);
        return $response;
    }
}
```

### 4. Вызовите API

```bash
curl -X POST http://localhost/api/v1 \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc": "2.0", "method": "getProduct", "params": {"id": 1, "title": "test"}, "id": "1"}'
```

Ответ:

```json
{
    "jsonrpc": "2.0",
    "result": {
        "success": true,
        "title": "Iphone 15",
        "price": 2000
    },
    "id": "1"
}
```

---

## Архитектура

### Пайплайн обработки запроса

```
HTTP POST /api/v{version}
    |
    v
ApiController
    |
    v
RequestRawDataHandler --- парсит HTTP-запрос (JSON body / query params)
    |
    v
BatchStrategyFactory --- определяет: одиночный или пакетный запрос
    |
    v
RequestHandler
    |--- Поиск MethodSpec по имени метода
    |--- Создание объекта Request из параметров
    |--- Валидация типизированных свойств
    |--- PreProcessors (если есть)
    |--- Method::call(Request) -> Response
    |--- PostProcessors (если есть)
    |
    v
ResponseService --- сериализация ответа в JSON-RPC 2.0 формат
```

### Структура проекта API-метода

```
src/RPC/V1/
    GetProductMethod.php          # Класс метода с #[JsonRPCAPI] атрибутом
    GetProduct/
        Request.php               # DTO входящего запроса
        Response.php              # DTO ответа
```

Классы, реализующие `ApiMethodInterface` и помеченные атрибутом `#[JsonRPCAPI]`, автоматически обнаруживаются и регистрируются бандлом.

---

## Примеры

| Пример | Описание | Файлы |
|:------:|:--------:|:-----:|
| [Базовый](./docs/examples/base.ru.md) | Простейший пример создания API-метода | Request, Response, Method |
| [Pre/Post-процессоры](./docs/examples/pre-and-post-processors.ru.md) | Выполнение логики до и после вызова метода | Request, Response, Method, AbstractMethod |
| [Массив объектов](./docs/examples/array_of_objects.ru.md) | Возврат коллекции объектов в ответе | Request, Response, Method, Product |
| [Бинарный ответ](./docs/examples/plain_response.ru.md) | Возврат изображений, документов и других бинарных данных | Request, PlainResponse, Method |

---

## Дополнительная документация

| Раздел | Описание |
|--------|----------|
| [Обработка ошибок](./docs/errors.ru.md) | Коды ошибок, `JRPCException`, кастомные ошибки, `additionalInfo` |
| [Notification-запросы](./docs/notifications.ru.md) | Запросы без `id`, параметр `strict_notifications` |
| [Валидация параметров](./docs/validation.ru.md) | Автоматическая валидация типов, nullable, формат ошибок |
| [Базовый класс JsonRpcRequest](./docs/json_rpc_request.ru.md) | Метод `toArray()`, рекурсивная сериализация |
| [Partial updates (JSON Merge Patch)](./docs/partial_updates.ru.md) | `PartialRequestInterface`, `wasProvided()`, RFC 7396 семантика |
| [Troubleshooting / FAQ](./docs/troubleshooting.ru.md) | Типичные проблемы и их решения |
| [**Гайд по миграции 4.x → 5.0**](./docs/upgrade-5.0.md) | **Все ломающие изменения 5.0: что сломается и что с этим делать** |
| [CHANGELOG](./CHANGELOG.ru.md) | История изменений по версиям |

---

## Logging

Опциональная подсистема Request/Response логирования с маскировкой sensitive-данных через PSR-3 logger. По умолчанию выключена. Подробности — [docs/logging.md](docs/logging.ru.md).

---

## Partial updates (JSON Merge Patch)

Бандл поддерживает PATCH-семантику по [RFC 7396](https://datatracker.ietf.org/doc/html/rfc7396) для Update-методов, где клиент шлёт только изменённые поля.

**Проблема:** при стандартном паттерне `if ($request->getX() !== null) { $entity->setX($request->getX()); }` нельзя различить «поле не передано» и «поле передано как `null`» — оба случая дают `null` в DTO. Это значит, что нельзя **очистить** поле через PATCH.

**Решение:** Request DTO реализует `PartialRequestInterface`, и фреймворк отслеживает, какие поля реально пришли в payload. Сервис-слой использует `wasProvided('x')` вместо `!== null`:

```php
use OV\JsonRPCAPIBundle\Core\Request\PartialUpdateRequest;

class UpdateUserRequest extends PartialUpdateRequest
{
    private ?int $id = null;
    private ?string $email = null;
    private ?string $bio = null;
    // getters/setters...
}
```

```php
public function call(UpdateUserRequest $request): Response
{
    $user = $this->userRepository->find($request->getId());

    if ($request->wasProvided('email')) {
        $user->setEmail($request->getEmail()); // null = очистить
    }
    if ($request->wasProvided('bio')) {
        $user->setBio($request->getBio());
    }
    // ...
}
```

**Семантика payload-а:**

| Payload | `wasProvided` | Поведение в сервисе |
|---|---|---|
| `{"email": "new@x.com"}` | `true` | установить новое значение |
| `{"email": null}` | `true` | очистить поле (`null`) |
| `{}` (ключ отсутствует) | `false` | не трогать поле |

**Опт-ин:** только DTO, реализующие `PartialRequestInterface`, получают трекинг. Существующие DTO работают без изменений (полная обратная совместимость).

Подробности и edge-кейсы — в [docs/partial_updates.md](./docs/partial_updates.ru.md).

---

## Версионирование API

Версия API определяется из URL (`/api/v1`, `/api/v2`) или явно через параметр `version` в атрибуте:

```php
#[JsonRPCAPI(methodName: 'getProduct', type: 'POST', version: 2)]
```

Если `version` не указан, он извлекается из пространства имён класса (например, `App\RPC\V1` -> версия 1).

---

## Пакетные запросы (Batch)

Бандл поддерживает пакетные JSON-RPC запросы согласно спецификации:

```bash
curl -X POST http://localhost/api/v1 \
  -H "Content-Type: application/json" \
  -d '[
    {"jsonrpc": "2.0", "method": "sum", "params": [1, 2, 4], "id": "1"},
    {"jsonrpc": "2.0", "method": "notify_hello", "params": [7]},
    {"jsonrpc": "2.0", "method": "subtract", "params": [42, 23], "id": "2"}
  ]'
```

---

## Pre- и Post-процессоры

Процессоры позволяют выполнять логику до и после вызова метода API (логирование, аудит, уведомления и т.д.):

```php
use OV\JsonRPCAPIBundle\Core\PreProcessorInterface;
use OV\JsonRPCAPIBundle\Core\PostProcessorInterface;

#[JsonRPCAPI(methodName: 'getProduct', type: 'POST')]
class GetProductMethod implements PreProcessorInterface, PostProcessorInterface
{
    public function getPreProcessors(): array
    {
        return [
            static::class => ['logRequest'],
        ];
    }

    public function getPostProcessors(): array
    {
        return [
            static::class => ['logResponse'],
        ];
    }

    public function logRequest(string $processorClass, ?object $request = null): void
    {
        // Вызывается ПЕРЕД call()
    }

    public function logResponse(string $processorClass, ?object $request = null, ?OvResponseInterface $response = null): void
    {
        // Вызывается ПОСЛЕ call()
    }

    public function call(Request $request): Response
    {
        // Основная логика
    }
}
```

Подробнее: [docs/examples/pre-and-post-processors.md](./docs/examples/pre-and-post-processors.ru.md)

---

## Swagger / OpenAPI

### Генерация документации

```bash
bin/console ov:swagger:generate
```

Генерирует файл `public/openapi/api_v1.yaml`, который можно использовать в Swagger UI.

### Аннотации для документации

**Скалярные свойства:**

```php
use OV\JsonRPCAPIBundle\Core\Annotation\SwaggerProperty;

class Response
{
    #[SwaggerProperty(default: 'true', example: 'true')]
    private bool $success;

    #[SwaggerProperty(format: 'email', example: 'user@example.com')]
    private string $email;
}
```

**Массивы:**

```php
use OV\JsonRPCAPIBundle\Core\Annotation\SwaggerArrayProperty;

class Response
{
    #[SwaggerArrayProperty(type: 'string')]
    private array $errors = [];

    #[SwaggerArrayProperty(type: Product::class, ofClass: true)]
    private array $products = [];
}
```

**Теги для группировки:**

```php
#[JsonRPCAPI(methodName: 'getProduct', type: 'POST', tags: ['products'])]
```

Подробнее:
- [Теги](./docs/swagger/tags.ru.md)
- [Скалярные свойства](./docs/swagger/scalar.ru.md)
- [Массивы](./docs/swagger/array.ru.md)

---

## Безопасность

### Ролевой доступ

Ограничение доступа к методам по ролям через атрибут `roles`:

```php
#[JsonRPCAPI(
    methodName: 'deleteUser',
    type: 'POST',
    roles: ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']
)]
class DeleteUserMethod implements ApiMethodInterface
{
    public function call(Request $request): Response { /* ... */ }
}
```

При отсутствии нужной роли бандл возвращает обычный JSON-RPC error-объект с кодом `-32000` и HTTP-статусом 200 — **не** HTTP 403: `{"jsonrpc": "2.0", "error": {"code": -32000, "message": "Access denied."}, "id": ...}`.

### Аутентификация

Бандл совместим с любым способом аутентификации Symfony:

- [JWT-токены через lexik/jwt-authentication-bundle](./docs/security/jwt_bundle.ru.md)
- [Кастомная токенная аутентификация](./docs/security/self_made_token.ru.md)
- [Ролевая модель](./docs/security/roles.ru.md)

---

## Тестирование

Запуск тестов:

```bash
./vendor/bin/phpunit tests/
```

Покрытие требует драйвера (xdebug или pcov). В `phpunit.xml.dist` уже настроен `<source>`-блок для отчётов покрытия PHPUnit 10+.

Мутационное тестирование (также требует драйвер покрытия):

```bash
composer infection
```

Тестовый набор включает:
- **Unit-тесты** — все Core-компоненты, сервисы, модели запросов/ответов, DI, Swagger-модели.
- **Интеграционные тесты** — полный цикл обработки запросов через контроллер.
- **Тесты команд** — генерация Swagger YAML.
- **Security regression-тесты** (`tests/Security/`) — DoS-лимиты (payload, batch, DTO depth, array size), sanitization ошибок, CORS origin matching, проверки видимости сеттеров, path-containment команды.

Гайд по написанию тестов для собственных RPC-методов — [docs/testing.md](./docs/testing.ru.md).

---

## Конфигурация

### Параметры `ov_json_rpc_api`

| Параметр | По умолчанию | Описание |
|----------|:------------:|----------|
| `access_control_allow_origin_list` | `[]` | Разрешённые CORS-домены. `['*']` — wildcard; список конкретных origin'ов матчится с заголовком запроса `Origin`. Для origin'ов вне списка CORS-заголовок не отдаётся вообще — легаси comma-joined режима не существует. |
| `cors_allowed_headers` | `['Content-Type']` | Заголовки, разрешённые в ответе на CORS preflight (`Access-Control-Allow-Headers`). Бандл обрабатывает `OPTIONS` самостоятельно. |
| `strict_notifications` | `true` | Строгое следование JSON-RPC 2.0 для Notification-запросов (без `id`). При `true` — сервер не возвращает ответ (по спеку). При `false` — лояльный режим: ответ возвращается, если результат непустой (поведение 3.x). |
| `allow_extra_fields` | `false` | При `false` — лишние поля в params, отсутствующие в Request DTO, вызывают `INVALID_PARAMS`. Можно переопределить per-method через `#[JsonRPCAPI(allowExtraFields: true)]`. |
| `expose_internal_errors` | `false` | При `false` (production-safe) — uncaught non-`JRPCException` исключения возвращаются клиенту как `Internal error.`, а оригинал пишется в LoggerInterface. При `true` — сырое сообщение отдаётся клиенту (для dev). |
| `max_payload_bytes` | `1048576` | Максимальный размер сырого тела запроса в байтах. Большие запросы — `INVALID_REQUEST`. |
| `max_json_depth` | `64` | Максимальная глубина вложенности JSON при декодировании. Глубже — `PARSE_ERROR`. |
| `max_batch_size` | `50` | Максимальное число запросов в одном JSON-RPC batch'е. Больше — единый `INVALID_REQUEST`. |
| `max_dto_depth` | `10` | Максимальная глубина рекурсии при гидратации вложенных Request DTO. Защита от stack/memory exhaustion. |
| `max_array_param_size` | `1000` | Максимальное число элементов массива-параметра, обрабатываемого через `addX()`-адеры. |
| `logging.enabled` | `false` | Логирование запросов и ответов. Всё остальное в блоке `logging.*` действует только при `true`. |
| `logging.request_level` | `'info'` | PSR-3 уровень записи входящего запроса. |
| `logging.response_level` | `'info'` | PSR-3 уровень записи успешного ответа. |
| `logging.error_response_level` | `'warning'` | PSR-3 уровень записи ответа с ошибкой. |
| `logging.max_body_length` | `8192` | Обрезка тела запроса и ответа в логе, в символах. `0` — не обрезать. **В 4.x дефолт был `0`.** |
| `logging.skip_plain_responses` | `true` | Не писать в лог тела `PlainResponseInterface`-ответов (файлы, потоки). |
| `logging.logger_service` | `null` | ID сервиса PSR-3 логгера, в который пишет `JsonRpcCallLogger`. |
| `logging.call_logger_service` | `null` | ID сервиса, целиком заменяющего реализацию `JsonRpcCallLoggerInterface`. |
| `logging.masking.placeholder` | `'***'` | Чем заменяется значение поля, попавшего под маскирование. |
| `logging.masking.key_patterns` | 29 паттернов | Регулярные выражения имён полей и заголовков, значения которых маскируются (`password`, `token`, `secret`, `authorization`, `jwt` и прочие). **В 4.x дефолт был `[]`, то есть маскирования не было.** Битое регулярное выражение роняет компиляцию контейнера. |
| `swagger` | — | Конфигурация Swagger по версиям API |
| `swagger.*.api_version` | `'1'` | Номер версии API |
| `swagger.*.base_path` | — | URL production-сервера |
| `swagger.*.test_path` | `null` | URL тестового сервера |
| `swagger.*.base_path_variables` | `[]` | Переменные для подстановки в base_path |
| `swagger.*.test_path_variables` | `[]` | Переменные для подстановки в test_path |
| `swagger.*.auth_token_name` | — | Имя заголовка для токена авторизации |
| `swagger.*.auth_token_test_value` | — | Тестовое значение токена. **Сейчас не используется:** в OpenAPI-схему попадает только имя заголовка (`auth_token_name`), значение никуда не подставляется. Ключ сохранён, чтобы не ломать существующие конфиги. |
| `swagger.*.info` | — | Информация об API (title, description, contact, license) |

> **Security hardening:** рекомендации по значениям, обоснование и тюнинг для high-volume API — [docs/security_hardening.md](./docs/security_hardening.ru.md).

---

## Параметры атрибута `#[JsonRPCAPI]`

| Параметр | Тип | Обязательный | По умолчанию | Описание |
|----------|-----|:------------:|:------------:|----------|
| `methodName` | string | да | — | Имя JSON-RPC метода |
| `type` | string | да | — | HTTP-метод (POST, GET, PUT, PATCH, DELETE) |
| `version` | ?int | нет | `null` | Версия API (если null — определяется из namespace) |
| `summary` | string | нет | `''` | Краткое описание для Swagger |
| `description` | string | нет | `''` | Подробное описание для Swagger |
| `tags` | ?array | нет | `null` | Теги для группировки в Swagger |
| `roles` | array | нет | `[]` | Требуемые роли для доступа |
| `ignoreInSwagger` | bool | нет | `false` | Исключить метод из Swagger-документации |
| `group` | ?string | нет | `null` | Группа для пути в Swagger (например, `'products'` → `/products/get_product`) |
| `allowExtraFields` | bool | нет | `false` | Разрешить в `params` поля, не объявленные в Request DTO. Переопределяет глобальный `allow_extra_fields` для этого метода и действует на любой глубине вложенности |

---

## Коды ошибок JSON-RPC

| Код | Константа | Описание |
|-----|-----------|----------|
| `-32700` | `PARSE_ERROR` | Ошибка парсинга JSON |
| `-32600` | `INVALID_REQUEST` | Невалидный JSON-RPC запрос |
| `-32601` | `METHOD_NOT_FOUND` | Метод не найден |
| `-32602` | `INVALID_PARAMS` | Невалидные параметры |
| `-32603` | `INTERNAL_ERROR` | Внутренняя ошибка |
| `-32000` | `SERVER_ERROR` | Серверная ошибка |

---

## Версионирование и обратная совместимость

Бандл следует [семантическому версионированию](https://semver.org/lang/ru/):

- **Мажорные** релизы (`4.0`, `5.0`, ...) могут менять поведение и публичный API. Каждое ломающее изменение перечислено в [CHANGELOG.md](./CHANGELOG.ru.md) и сопровождается гайдом по миграции ([docs/upgrade-5.0.md](./docs/upgrade-5.0.ru.md), [docs/upgrade-4.0.md](./docs/upgrade-4.0.ru.md)).
- **Минорные** релизы добавляют функциональность, не ломая существующие интеграции; поведение при существующих значениях конфигурации по умолчанию не меняется.
- **Патчи** — только исправления багов и уязвимостей.

Обещание обратной совместимости покрывает всё, чего касается интегратор:

- схему конфигурации `ov_json_rpc_api.*` и её значения по умолчанию;
- атрибут `#[JsonRPCAPI]` и его параметры;
- проволочный формат — структуры запроса, ответа, ошибки и батча JSON-RPC 2.0;
- интерфейсы, которые интегратор реализует или использует: `ApiMethodInterface`, `PreProcessorInterface`, `PostProcessorInterface`, `PlainResponseInterface`, `JsonRpcCallLoggerInterface`, `JsonRpcLogFormatterInterface`, `SensitiveDataMaskerInterface`, `ContextIdGeneratorInterface`;
- команду `ov:swagger:generate` и структуру генерируемого OpenAPI.

Внутренние сервисные классы, не перечисленные выше, — деталь реализации и могут меняться в минорных релизах. Функциональность, которую предстоит удалить в следующем мажоре, по возможности объявляется устаревшей в предшествующем миноре с пометкой в CHANGELOG.

Список версий, получающих исправления, — в [SECURITY.md](./SECURITY.ru.md#поддерживаемые-версии).

---

## Вклад в проект

См. [CONTRIBUTING.md](./CONTRIBUTING.ru.md) — окружение, тесты, требования к PR. Об уязвимостях — см. [SECURITY.md](./SECURITY.ru.md), не через публичный issue.

## Лицензия

[MIT](https://opensource.org/licenses/MIT)

## Автор

Leonid Groshev — [OtezVikentiy@gmail.com](mailto:OtezVikentiy@gmail.com) — [otezvikentiy.tech](https://otezvikentiy.tech)
