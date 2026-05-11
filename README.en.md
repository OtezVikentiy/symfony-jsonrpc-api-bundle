# OtezVikentiy Symfony JSON-RPC API Bundle

[Русская версия](./README.md)

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://php.net/)
[![Symfony Version](https://img.shields.io/badge/symfony-%3E%3D6.4-000000.svg)](https://symfony.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Version](https://img.shields.io/badge/version-4.0-blue.svg)](https://github.com/OtezVikentiy/symfony-jsonrpc-api-bundle)

A Symfony bundle for fast and convenient creation of JSON-RPC 2.0 API applications.

GitHub: https://github.com/OtezVikentiy/symfony-jsonrpc-api-bundle

---

## Features

- Full [JSON-RPC 2.0](https://www.jsonrpc.org/specification) specification compliance
- Method configuration via PHP 8 attributes (`#[JsonRPCAPI(...)]`)
- HTTP methods support: POST, GET, PUT, PATCH, DELETE
- API versioning (`/api/v1`, `/api/v2`, ...)
- Automatic OpenAPI/Swagger documentation generation
- Pre- and Post-processors (middleware)
- Batch requests
- Built-in request validation
- Role-based access control via Symfony Security
- Binary response support (images, documents)

---

## Requirements

- PHP >= 8.2
- Symfony >= 6.4

---

## Installation

```bash
composer require otezvikentiy/json-rpc-api
```

Enable the bundle (if not using Symfony Flex):

```php
// config/bundles.php
return [
    // ...
    OV\JsonRPCAPIBundle\OVJsonRPCAPIBundle::class => ['all' => true],
];
```

Create configuration files:

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

Detailed instructions: [docs/installation.md](./docs/installation.md)

---

## Quick Start

### 1. Create a Request

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

### 2. Create a Response

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

### 3. Create an API Method

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

### 4. Call the API

```bash
curl -X POST http://localhost/api/v1 \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc": "2.0", "method": "getProduct", "params": {"id": 1, "title": "test"}, "id": "1"}'
```

Response:

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

## Architecture

### Request Processing Pipeline

```
HTTP POST /api/v{version}
    |
    v
ApiController
    |
    v
RequestRawDataHandler --- parses HTTP request (JSON body / query params)
    |
    v
BatchStrategyFactory --- determines: single or batch request
    |
    v
RequestHandler
    |--- Lookup MethodSpec by method name
    |--- Create Request object from parameters
    |--- Validate typed properties
    |--- PreProcessors (if any)
    |--- Method::call(Request) -> Response
    |--- PostProcessors (if any)
    |
    v
ResponseService --- serializes response into JSON-RPC 2.0 format
```

### API Method Project Structure

```
src/RPC/V1/
    GetProductMethod.php          # Method class with #[JsonRPCAPI] attribute
    GetProduct/
        Request.php               # Incoming request DTO
        Response.php              # Response DTO
```

Classes marked with the `#[JsonRPCAPI]` attribute are automatically discovered and registered by the bundle.

---

## Examples

| Example | Description | Files |
|:-------:|:----------:|:-----:|
| [Basic](./docs/examples/base.md) | Simplest example of creating an API method | Request, Response, Method |
| [Pre/Post-processors](./docs/examples/pre-and-post-processors.md) | Executing logic before and after method call | Request, Response, Method, AbstractMethod |
| [Array of objects](./docs/examples/array_of_objects.md) | Returning a collection of objects in the response | Request, Response, Method, Product |
| [Binary response](./docs/examples/plain_response.md) | Returning images, documents and other binary data | Request, PlainResponse, Method |

---

## Additional Documentation

| Section | Description |
|---------|-------------|
| [Error Handling](./docs/errors.md) | Error codes, `JRPCException`, custom errors, `additionalInfo` |
| [Notification Requests](./docs/notifications.md) | Requests without `id`, `strict_notifications` parameter |
| [Parameter Validation](./docs/validation.md) | Automatic type validation, nullable, error format |
| [JsonRpcRequest Base Class](./docs/json_rpc_request.md) | `toArray()` method, recursive serialization |
| [Partial updates (JSON Merge Patch)](./docs/partial_updates.md) | `PartialRequestInterface`, `wasProvided()`, RFC 7396 semantics |
| [Troubleshooting / FAQ](./docs/troubleshooting.md) | Common problems and their solutions |
| [CHANGELOG](./CHANGELOG.md) | Version history |

---

## Partial updates (JSON Merge Patch)

The bundle supports PATCH semantics per [RFC 7396](https://datatracker.ietf.org/doc/html/rfc7396) for Update methods where the client sends only changed fields.

**Problem:** the standard `if ($request->getX() !== null) { $entity->setX($request->getX()); }` pattern cannot distinguish "field not in payload" from "field sent as `null`" — both give `null` on the DTO. This means a field cannot be **cleared** via PATCH.

**Solution:** the Request DTO implements `PartialRequestInterface`, and the framework tracks which fields actually arrived in the payload. The service layer uses `wasProvided('x')` instead of `!== null`:

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
        $user->setEmail($request->getEmail()); // null = clear
    }
    if ($request->wasProvided('bio')) {
        $user->setBio($request->getBio());
    }
    // ...
}
```

**Payload semantics:**

| Payload | `wasProvided` | Service behavior |
|---|---|---|
| `{"email": "new@x.com"}` | `true` | set the new value |
| `{"email": null}` | `true` | clear the field (`null`) |
| `{}` (key absent) | `false` | leave the field untouched |

**Opt-in:** only DTOs implementing `PartialRequestInterface` get tracking. Existing DTOs work without changes (full backward compatibility).

Details and edge cases — in [docs/partial_updates.md](./docs/partial_updates.md).

---

## API Versioning

API version is determined from the URL (`/api/v1`, `/api/v2`) or explicitly via the `version` parameter in the attribute:

```php
#[JsonRPCAPI(methodName: 'getProduct', type: 'POST', version: 2)]
```

If `version` is not specified, it's extracted from the class namespace (e.g., `App\RPC\V1` -> version 1).

---

## Batch Requests

The bundle supports batch JSON-RPC requests per specification:

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

## Pre- and Post-processors

Processors allow executing logic before and after API method calls (logging, audit, notifications, etc.):

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
        // Called BEFORE call()
    }

    public function logResponse(string $processorClass, ?object $request = null, ?OvResponseInterface $response = null): void
    {
        // Called AFTER call()
    }

    public function call(Request $request): Response
    {
        // Main logic
    }
}
```

Details: [docs/examples/pre-and-post-processors.md](./docs/examples/pre-and-post-processors.md)

---

## Swagger / OpenAPI

### Generating Documentation

```bash
bin/console ov:swagger:generate
```

Generates `public/openapi/api_v1.yaml` file for use with Swagger UI.

### Documentation Annotations

**Scalar properties:**

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

**Arrays:**

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

**Tags for grouping:**

```php
#[JsonRPCAPI(methodName: 'getProduct', type: 'POST', tags: ['products'])]
```

Details:
- [Tags](./docs/swagger/tags.md)
- [Scalar properties](./docs/swagger/scalar.md)
- [Arrays](./docs/swagger/array.md)

---

## Security

### Role-Based Access

Restrict method access by roles via the `roles` attribute:

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

Returns HTTP 403 if the user lacks the required role.

### Authentication

The bundle is compatible with any Symfony authentication method:

- [JWT tokens via lexik/jwt-authentication-bundle](./docs/security/jwt_bundle.md)
- [Custom token authentication](./docs/security/self_made_token.md)
- [Role model](./docs/security/roles.md)

---

## Testing

Run the test suite:

```bash
./vendor/bin/phpunit tests/
```

Coverage reports require a coverage driver (xdebug or pcov). The `phpunit.xml.dist` config already declares the `<source>` block for PHPUnit 10+ coverage output.

The bundled test suite covers:
- **Unit tests** — every Core component, services, request/response models, DI, Swagger models.
- **Integration tests** — full request lifecycle through the controller.
- **Command tests** — Swagger YAML generation.
- **Security regression tests** (`tests/Security/`) — DoS limits (payload, batch, DTO depth, array size), error sanitization, CORS origin matching, setter visibility, command path containment.

See [docs/testing.md](./docs/testing.md) for guidance on writing tests for your own RPC methods.

---

## Configuration

### `ov_json_rpc_api` Parameters

| Parameter | Default | Description |
|-----------|:-------:|-------------|
| `access_control_allow_origin_list` | `[]` | Allowed CORS origins. Use `['*']` for wildcard, or list exact origins for matching against the request `Origin` header. |
| `cors_strict` | `true` | When `true`, origins not in the whitelist receive no CORS header. When `false`, falls back to the legacy comma-joined header (non-compliant; for backwards compatibility only). |
| `strict_notifications` | `true` | Strict JSON-RPC 2.0 Notification compliance. When `true` — server does not respond to notifications (per spec). When `false` — server returns a response even for notifications if the result is non-empty (legacy 3.x behaviour). |
| `allow_extra_fields` | `false` | When `false`, request params containing fields not declared on the Request DTO are rejected with `INVALID_PARAMS`. Can be overridden per-method via the `#[JsonRPCAPI(allowExtraFields: true)]` attribute. |
| `expose_internal_errors` | `false` | When `false` (production-safe), uncaught non-`JRPCException` throwables are replaced with a generic `Internal error.` payload and the original is sent to the logger. Set to `true` only in dev to expose raw exception messages. |
| `max_payload_bytes` | `1048576` | Maximum bytes accepted for the raw request body. Larger requests are rejected with `INVALID_REQUEST`. |
| `max_json_depth` | `64` | Maximum allowed JSON nesting depth when decoding the payload. Deeper inputs are rejected with `PARSE_ERROR`. |
| `max_batch_size` | `50` | Maximum number of requests allowed in a single JSON-RPC batch. Larger batches return a single `INVALID_REQUEST` error. |
| `max_dto_depth` | `10` | Maximum recursion depth when hydrating nested Request DTO objects. Prevents stack/memory exhaustion via deeply nested payloads. |
| `max_array_param_size` | `1000` | Maximum element count for array parameters bound through `addX()` adders. |
| `swagger` | — | Swagger configuration per API version. |
| `swagger.*.api_version` | `'1'` | API version number. |
| `swagger.*.base_path` | — | Production server URL. |
| `swagger.*.test_path` | `null` | Test server URL. |
| `swagger.*.base_path_variables` | `[]` | Variables for base_path substitution. |
| `swagger.*.test_path_variables` | `[]` | Variables for test_path substitution. |
| `swagger.*.auth_token_name` | — | Authorization token header name. |
| `swagger.*.auth_token_test_value` | — | Test token value. |
| `swagger.*.info` | — | API information (title, description, contact, license). |

> **Security hardening:** see [docs/security_hardening.md](./docs/security_hardening.md) for recommended values, rationale, and tuning tips for high-volume APIs.

---

## `#[JsonRPCAPI]` Attribute Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|:--------:|:-------:|-------------|
| `methodName` | string | yes | — | JSON-RPC method name |
| `type` | string | yes | — | HTTP method (POST, GET, PUT, PATCH, DELETE) |
| `version` | ?int | no | `null` | API version (if null — determined from namespace) |
| `summary` | string | no | `''` | Short description for Swagger |
| `description` | string | no | `''` | Detailed description for Swagger |
| `tags` | ?array | no | `null` | Tags for Swagger grouping |
| `roles` | array | no | `[]` | Required roles for access |
| `ignoreInSwagger` | bool | no | `false` | Exclude method from Swagger documentation |
| `group` | ?string | no | `null` | Swagger path group (e.g., `'products'` -> `/products/get_product`) |

---

## JSON-RPC Error Codes

| Code | Constant | Description |
|------|----------|-------------|
| `-32700` | `PARSE_ERROR` | JSON parsing error |
| `-32600` | `INVALID_REQUEST` | Invalid JSON-RPC request |
| `-32601` | `METHOD_NOT_FOUND` | Method not found |
| `-32602` | `INVALID_PARAMS` | Invalid parameters |
| `-32603` | `INTERNAL_ERROR` | Internal error |
| `-32000` | `SERVER_ERROR` | Server error |

---

## License

[MIT](https://opensource.org/licenses/MIT)

## Author

Leonid Groshev — [OtezVikentiy@gmail.com](mailto:OtezVikentiy@gmail.com) — [otezvikentiy.tech](https://otezvikentiy.tech)
