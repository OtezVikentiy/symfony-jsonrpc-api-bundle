# Массив объектов в ответе

---

## Описание

Этот пример показывает, как возвращать список объектов в ответе API-метода. Такой подход часто нужен для списков с фильтрацией — например, список товаров в интернет-магазине.

---

## Request

Если вы хотите удобно получать данные запроса в виде массива, наследуйте Request от `JsonRpcRequest` — это даст доступ к методу `toArray()`.

Метод `addCategory()` позволяет принимать массив категорий поэлементно (pattern "adder").

```php
<?php
// src/RPC/V1/GetProducts/Request.php

namespace App\RPC\V1\GetProducts;

use OV\JsonRPCAPIBundle\Core\Request\JsonRpcRequest;

class Request extends JsonRpcRequest
{
    private ?string $title = null;
    private ?int $priceFrom = null;
    private ?int $priceTo = null;
    private ?array $categories = null;

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title): void { $this->title = $title; }

    public function getPriceFrom(): ?int { return $this->priceFrom; }
    public function setPriceFrom(?int $priceFrom): void { $this->priceFrom = $priceFrom; }

    public function getPriceTo(): ?int { return $this->priceTo; }
    public function setPriceTo(?int $priceTo): void { $this->priceTo = $priceTo; }

    public function getCategories(): ?array { return $this->categories; }
    public function setCategories(?array $categories): void { $this->categories = $categories; }

    public function addCategory(int $category): void
    {
        $this->categories[] = $category;
    }
}
```

## DTO объекта (Product)

```php
<?php
// src/RPC/V1/GetProducts/Product.php

namespace App\RPC\V1\GetProducts;

class Product
{
    private string $title;
    private int $price;

    public function getTitle(): string { return $this->title; }

    public function setTitle(string $title): Product
    {
        $this->title = $title;
        return $this;
    }

    public function getPrice(): int { return $this->price; }

    public function setPrice(int $price): Product
    {
        $this->price = $price;
        return $this;
    }
}
```

## Response

Метод `addProduct()` позволяет добавлять объекты по одному. Бандл автоматически распознаёт "adder"-методы (с префиксом `add`) и использует их для заполнения массивов типизированными объектами.

```php
<?php
// src/RPC/V1/GetProducts/Response.php

namespace App\RPC\V1\GetProducts;

class Response
{
    private bool $success;
    private array $errors;
    private array $products = [];

    public function __construct(bool $success = true, array $errors = [])
    {
        $this->success = $success;
        $this->errors = $errors;
    }

    public function isSuccess(): bool { return $this->success; }
    public function setSuccess(bool $success): void { $this->success = $success; }

    public function getErrors(): array { return $this->errors; }
    public function setErrors(array $errors): Response { $this->errors = $errors; return $this; }
    public function addError(string $error): Response { $this->errors[] = $error; return $this; }

    public function getProducts(): array { return $this->products; }
    public function setProducts(array $products): Response { $this->products = $products; return $this; }

    public function addProduct(Product $product): Response
    {
        $this->products[] = $product;
        return $this;
    }
}
```

## Метод API

```php
<?php
// src/RPC/V1/GetProductsMethod.php

namespace App\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use App\RPC\V1\GetProducts\Request;
use App\RPC\V1\GetProducts\Response;
use App\RPC\V1\GetProducts\Product as ApiProductDto;
use App\Repository\ProductRepository;

#[JsonRPCAPI(methodName: 'getProducts', type: 'POST')]
class GetProductsMethod
{
    public function __construct(
        private readonly ProductRepository $productRepository,
    ) {}

    public function call(Request $request): Response
    {
        // toArray() доступен благодаря наследованию от JsonRpcRequest
        $filter = $request->toArray();

        $products = $this->productRepository->findBy($filter);

        $response = new Response();

        foreach ($products as $product) {
            $response->addProduct(
                (new ApiProductDto())
                ->setTitle($product->getTitle())
                ->setPrice($product->getPrice())
            );
        }

        return $response;
    }
}
```

## Пример вызова

```bash
curl -X POST http://localhost/api/v1 \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc": "2.0", "method": "getProducts", "params": {"title": "iphone", "priceFrom": 100, "priceTo": 5000}, "id": 1}'
```

Ответ:

```json
{
    "jsonrpc": "2.0",
    "result": {
        "success": true,
        "errors": [],
        "products": [
            {"title": "Iphone 14", "price": 1500},
            {"title": "Iphone 15", "price": 2000}
        ]
    },
    "id": "1"
}
```

## Зачем нужен отдельный DTO?

Использование отдельного DTO (`Product`) вместо прямой отдачи entity может показаться избыточным, но на практике это оправдано:

- Можно контролировать, какие именно поля отдаются клиенту
- Можно трансформировать формат данных
- Можно добавить вычисляемые поля
- Entity в базе данных могут содержать конфиденциальные поля, которые не нужно отдавать через API
