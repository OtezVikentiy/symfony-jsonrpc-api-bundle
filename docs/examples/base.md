# Базовый пример

---

## Описание

Простейший пример создания JSON-RPC API метода. Демонстрирует основные компоненты: Request, Response и класс метода с атрибутом `#[JsonRPCAPI]`.

---

## Request

Класс Request описывает входящие параметры запроса. Параметры, переданные в конструктор, становятся **обязательными**. Остальные свойства заполняются через setter-методы и являются необязательными.

```php
<?php
// src/RPC/V1/GetProduct/Request.php

namespace App\RPC\V1\GetProduct;

class Request
{
    private int $id;
    private string $title;

    /**
     * Параметр id передан в конструктор — он обязателен в запросе.
     * Параметр title устанавливается через setter — он необязателен.
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

Класс Response описывает структуру ответа. Все свойства с getter-методами автоматически сериализуются в JSON.

> Для `bool`-свойств используйте префикс `is` (например, `isSuccess()`), для остальных — `get`.

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

## Метод API

Класс метода помечается атрибутом `#[JsonRPCAPI]` и содержит метод `call()`, в который передаётся объект Request.

Версия API определяется автоматически из пространства имён (`App\RPC\V1` -> версия 1).
При необходимости можно указать версию явно: `#[JsonRPCAPI(methodName: 'getProduct', type: 'POST', version: 1)]`.

```php
<?php
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

## Пример вызова

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

## Структура файлов

```
src/RPC/V1/
    GetProductMethod.php
    GetProduct/
        Request.php
        Response.php
```
