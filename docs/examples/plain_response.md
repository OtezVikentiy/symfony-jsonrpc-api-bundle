# Бинарный ответ (PlainResponse)

---

## Описание

Иногда API должен возвращать не JSON, а бинарные данные — изображение, документ, PDF-файл и т.д.
Для этого используется `PlainResponseInterface`.

Если метод `call()` возвращает объект, реализующий `PlainResponseInterface`, бандл отдаст его как есть (без JSON-RPC обёртки), добавив только CORS-заголовки.

---

## Request

```php
<?php
// src/RPC/V1/GetProductDocument/Request.php

namespace App\RPC\V1\GetProductDocument;

class Request
{
    private int $id;

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
}
```

## PlainResponse

Класс ответа должен наследовать `Symfony\Component\HttpFoundation\Response` и реализовать `PlainResponseInterface`:

```php
<?php
// src/RPC/V1/GetProductDocument/PlainResponse.php

namespace App\RPC\V1\GetProductDocument;

use OV\JsonRPCAPIBundle\Core\Response\PlainResponseInterface;
use Symfony\Component\HttpFoundation\Response;

class PlainResponse extends Response implements PlainResponseInterface
{

}
```

## ErrorResponse (для ошибок)

Если в некоторых случаях нужно вернуть обычный JSON-ответ вместо бинарного (например, при ошибке), создайте отдельный класс. Он **не** реализует `PlainResponseInterface`, поэтому бандл обернёт его в стандартный JSON-RPC ответ:

```php
<?php
// src/RPC/V1/GetProductDocument/ErrorResponse.php

namespace App\RPC\V1\GetProductDocument;

class ErrorResponse
{
    private bool $success;
    private array $errors = [];

    public function __construct(bool $success = false) { $this->success = $success; }
    public function isSuccess(): bool { return $this->success; }
    public function getErrors(): array { return $this->errors; }
    public function addError(string $error): ErrorResponse { $this->errors[] = $error; return $this; }
}
```

## Метод API

В реальном коде метод часто возвращает **Union-тип**: бинарный ответ при успехе или JSON-ответ при ошибке.
Бандл автоматически определяет, реализует ли возвращённый объект `PlainResponseInterface`, и обрабатывает его соответственно.

```php
<?php
// src/RPC/V1/GetProductDocumentMethod.php

namespace App\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use App\RPC\V1\GetProductDocument\Request;
use App\RPC\V1\GetProductDocument\PlainResponse;
use App\RPC\V1\GetProductDocument\ErrorResponse;
use App\Repository\ProductRepository;

#[JsonRPCAPI(methodName: 'getProductDocument', type: 'POST')]
class GetProductDocumentMethod
{
    public function __construct(
        private readonly ProductRepository $productRepository
    ) {}

    public function call(Request $request): ErrorResponse|PlainResponse
    {
        $product = $this->productRepository->find($request->getId());

        if (is_null($product)) {
            return (new ErrorResponse())->addError('Product not found');
        }

        return new PlainResponse(
            content: $product->getDocumentContents(),
            headers: ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }
}
```

> **Как это работает внутри бандла:** в `RequestHandler::processBatch()` после вызова `call()` проверяется, реализует ли ответ `PlainResponseInterface`. Если да — ответ отдаётся напрямую с CORS-заголовками. Если нет — оборачивается в JSON-RPC 2.0 формат.

## Примеры Content-Type

| Тип данных | Content-Type |
|------------|-------------|
| PNG-изображение | `image/png` |
| JPEG-изображение | `image/jpeg` |
| PDF-документ | `application/pdf` |
| Excel (xlsx) | `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` |
| CSV | `text/csv` |
| ZIP-архив | `application/zip` |
