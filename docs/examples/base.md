[English](base.md) · [Русский](base.ru.md)

# A basic example

---

## What this shows

The simplest way to create a JSON-RPC API method. It demonstrates the main pieces: a Request, a Response, and the method class carrying the `#[JsonRPCAPI]` attribute.

---

## Request

The Request class describes the incoming parameters. Parameters passed to the constructor become **required**. The remaining properties are filled through setters and are optional.

```php
<?php
// src/RPC/V1/GetProduct/Request.php

namespace App\RPC\V1\GetProduct;

class Request
{
    private int $id;
    private string $title;

    /**
     * id is a constructor parameter, so the request must carry it.
     * title is set through a setter, so it is optional.
     */
    public function __construct(int $id)
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
```

## Response

The Response class describes the shape of the reply. What reaches the JSON is what the class exposes: a property with a public getter (`getX()`, `isX()`, or the bare accessor `x()`) **or** a public property — a promoted constructor parameter, for instance. A private or protected property with no public getter does not reach the response: that is how the leak was closed where Reflection read private fields on equal terms with the rest.

> For `bool` properties use the `is` prefix (`isSuccess()`); for the rest, `get`.

```php
<?php
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

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function setPrice(int $price): void
    {
        $this->price = $price;
    }
}
```

## The API method

The method class must **implement `ApiMethodInterface`** and carry the `#[JsonRPCAPI]` attribute. Both are required: the bundle registers only classes implementing the interface (the `ov.rpc.method` tag is applied automatically through `#[AutoconfigureTag]` on the interface itself), while the attribute describes the method's metadata. A class without the interface is not registered at all — a request for it returns `-32601 Method not found`, however correct the attribute may be. The class holds a `call()` method, which receives the Request object.

The API version is derived from the namespace (`App\RPC\V1` → version 1). It can be stated explicitly where needed: `#[JsonRPCAPI(methodName: 'getProduct', type: 'POST', version: 1)]`.

```php
<?php
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

## Calling it

```bash
curl -X POST http://localhost/api/v1 \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc": "2.0", "method": "getProduct", "params": {"id": 1, "title": "test"}, "id": 1}'
```

The response:

```json
{
    "jsonrpc": "2.0",
    "result": {
        "success": true,
        "title": "Iphone 15",
        "price": 2000
    },
    "id": 1
}
```

## File layout

```
src/RPC/V1/
    GetProductMethod.php
    GetProduct/
        Request.php
        Response.php
```
