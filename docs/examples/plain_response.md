[English](plain_response.md) · [Русский](plain_response.ru.md)

# A binary response (PlainResponse)

---

## What this shows

Sometimes an API has to return binary data rather than JSON — an image, a document, a PDF. `PlainResponseInterface` exists for that.

When `call()` returns an object implementing `PlainResponseInterface`, the bundle passes it through as-is, without the JSON-RPC envelope, adding only the CORS headers.

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

The response class must extend `Symfony\Component\HttpFoundation\Response` and implement `PlainResponseInterface`:

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

## ErrorResponse (for failures)

When some paths need an ordinary JSON reply instead of a binary one — on an error, say — create a separate class. It does **not** implement `PlainResponseInterface`, so the bundle wraps it in the standard JSON-RPC response:

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

## The API method

In real code a method often returns a **union type**: a binary response on success, a JSON one on failure. The bundle works out on its own whether the returned object implements `PlainResponseInterface` and handles it accordingly.

```php
<?php
// src/RPC/V1/GetProductDocumentMethod.php

namespace App\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\Core\ApiMethodInterface;
use App\RPC\V1\GetProductDocument\Request;
use App\RPC\V1\GetProductDocument\PlainResponse;
use App\RPC\V1\GetProductDocument\ErrorResponse;
use App\Repository\ProductRepository;

#[JsonRPCAPI(methodName: 'getProductDocument', type: 'POST')]
class GetProductDocumentMethod implements ApiMethodInterface
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

> **How this works inside the bundle:** in `RequestHandler::processBatch()`, after `call()` returns, the response is checked for `PlainResponseInterface`. If it implements it, the response goes out directly with CORS headers; if not, it is wrapped in the JSON-RPC 2.0 format.
>
> **One exception: inside a batch.** A batch response is a JSON array of objects, and a binary body does not fit inside one — such an element used to break the structure of the whole response. Since 5.0 a plain response returned from a batch element gives `-32603` with the explanation `Plain responses are not supported inside a batch request.` Call methods with binary responses as single requests.

## Content-Type examples

| Data | Content-Type |
|------|--------------|
| PNG image | `image/png` |
| JPEG image | `image/jpeg` |
| PDF document | `application/pdf` |
| Excel (xlsx) | `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` |
| CSV | `text/csv` |
| ZIP archive | `application/zip` |
