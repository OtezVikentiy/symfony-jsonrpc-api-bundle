# OtezVikentiy Symfony JSON-RPC API Bundle

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://php.net/)
[![Symfony Version](https://img.shields.io/badge/symfony-%3E%3D6.4-000000.svg)](https://symfony.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Version](https://img.shields.io/badge/version-3.3-blue.svg)](https://github.com/OtezVikentiy/symfony-jsonrpc-api-bundle)

Symfony-бандл для быстрого и удобного создания JSON-RPC 2.0 API приложений.

GitHub: https://github.com/OtezVikentiy/symfony-jsonrpc-api-bundle

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

- PHP >= 8.2
- Symfony >= 6.4

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

Подробная инструкция: [docs/installation.md](./docs/installation.md)

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
use App\RPC\V1\GetProduct\Request;
use App\RPC\V1\GetProduct\Response;

#[JsonRPCAPI(methodName: 'getProduct', type: 'POST')]
class GetProductMethod
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
  -d '{"jsonrpc": "2.0", "method": "getProduct", "params": {"id": 1, "title": "test"}, "id": 1}'
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
| [Базовый](./docs/examples/base.md) | Простейший пример создания API-метода | Request, Response, Method |
| [Pre/Post-процессоры](./docs/examples/pre-and-post-processors.md) | Выполнение логики до и после вызова метода | Request, Response, Method, AbstractMethod |
| [Массив объектов](./docs/examples/array_of_objects.md) | Возврат коллекции объектов в ответе | Request, Response, Method, Product |
| [Бинарный ответ](./docs/examples/simple_response.md) | Возврат изображений, документов и других бинарных данных | Request, PlainResponse, Method |

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

Подробнее: [docs/examples/pre-and-post-processors.md](./docs/examples/pre-and-post-processors.md)

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
    #[SwaggerProperty(default: true, example: true)]
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
- [Теги](./docs/swagger/tags.md)
- [Скалярные свойства](./docs/swagger/scalar.md)
- [Массивы](./docs/swagger/array.md)

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
class DeleteUserMethod
{
    public function call(Request $request): Response { /* ... */ }
}
```

При отсутствии нужной роли бандл возвращает HTTP 403.

### Аутентификация

Бандл совместим с любым способом аутентификации Symfony:

- [JWT-токены через lexik/jwt-authentication-bundle](./docs/security/jwt_bundle.md)
- [Кастомная токенная аутентификация](./docs/security/self_made_token.md)
- [Ролевая модель](./docs/security/roles.md)

---

## Тестирование

Запуск тестов:

```bash
./vendor/bin/phpunit tests/
```

Тестовый набор включает:
- **Unit-тесты** — все Core-компоненты, сервисы, модели запросов/ответов, DI, Swagger-модели
- **Интеграционные тесты** — полный цикл обработки запросов через контроллер
- **Тесты команд** — генерация Swagger YAML

---

## Конфигурация

### Параметры `ov_json_rpc_api`

| Параметр | Описание |
|----------|----------|
| `access_control_allow_origin_list` | Список разрешённых CORS-доменов |
| `strict_notifications` | Строгое следование спецификации JSON-RPC 2.0 для Notification-запросов (без `id`). При `true` — сервер не возвращает ответ. При `false` (по умолчанию) — ответ возвращается, если результат непустой |
| `swagger` | Конфигурация Swagger по версиям API |
| `swagger.*.api_version` | Номер версии API |
| `swagger.*.base_path` | URL production-сервера |
| `swagger.*.test_path` | URL тестового сервера |
| `swagger.*.base_path_variables` | Переменные для подстановки в base_path |
| `swagger.*.test_path_variables` | Переменные для подстановки в test_path |
| `swagger.*.auth_token_name` | Имя заголовка для токена авторизации |
| `swagger.*.auth_token_test_value` | Тестовое значение токена |
| `swagger.*.info` | Информация об API (title, description, contact, license) |

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

## Лицензия

[MIT](https://opensource.org/licenses/MIT)

## Автор

Leonid Groshev — [OtezVikentiy@gmail.com](mailto:OtezVikentiy@gmail.com) — [otezvikentiy.tech](https://otezvikentiy.tech)
