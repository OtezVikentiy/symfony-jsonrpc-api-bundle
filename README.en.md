# OtezVikentiy Symfony JSON-RPC API Bundle

[Русская версия](./README.md)

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://php.net/)
[![Symfony Version](https://img.shields.io/badge/symfony-%3E%3D6.4-000000.svg)](https://symfony.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Version](https://img.shields.io/badge/version-3.6-blue.svg)](https://github.com/OtezVikentiy/symfony-jsonrpc-api-bundle)

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
| [Troubleshooting / FAQ](./docs/troubleshooting.md) | Common problems and their solutions |
| [CHANGELOG](./CHANGELOG.md) | Version history |

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
    #[SwaggerProperty(default: true, example: true)]
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

## Configuration

### `ov_json_rpc_api` Parameters

| Parameter | Description |
|-----------|-------------|
| `access_control_allow_origin_list` | Allowed CORS domains list |
| `strict_notifications` | Strict JSON-RPC 2.0 Notification compliance. When `true` — server does not return response. When `false` (default) — response is returned if result is non-empty |
| `swagger` | Swagger configuration per API version |
| `swagger.*.api_version` | API version number |
| `swagger.*.base_path` | Production server URL |
| `swagger.*.test_path` | Test server URL |
| `swagger.*.base_path_variables` | Variables for base_path substitution |
| `swagger.*.test_path_variables` | Variables for test_path substitution |
| `swagger.*.auth_token_name` | Authorization token header name |
| `swagger.*.auth_token_test_value` | Test token value |
| `swagger.*.info` | API information (title, description, contact, license) |

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
