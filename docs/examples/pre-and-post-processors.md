# PreProcessor example

---

## Description

Every API endpoint can have multiple preprocessors.
Every preprocessor is called BEFORE the main logic of method is processed.
For example, method getProduct has a preprocessor for logging some data about request.

---

```php
<?php
// src/RPC/V1/GetProduct/Request.php

namespace App\RPC\V1\GetProduct;

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

Below is an abstract method from which all other
methods will be inherited (conditionally). It determines, depending on the method, which processors are used in a particular
case.
This approach will be useful in cases where you want, for example, to log calls to your endpoints
or, perhaps, send something to email for each call to some methods.

```php
<?php
// src/RPC/V1/AbstractMethod.php

namespace App\RPC\V1;

use OV\JsonRPCAPIBundle\Core\PreProcessorInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractMethod implements PreProcessorInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ){
    }

    public function getProcessors(): array
    {
        return [
            GetProductsMethod::class => ['log'],
        ];
    }

    public function log($request) {
        $this->logger->emergency('TEST TEST TEST ');
    }
}
```
```php
<?php
// src/RPC/V1/GetProduct.php

namespace App\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use App\RPC\V1\GetProduct\Request;
use App\RPC\V1\GetProduct\Response;

#[JsonRPCAPI(methodName: 'getProduct', type: 'POST')]
class GetProductMethod extends AbstractMethod
{
    /**
     * @param Request $request // !!!ATTENTION!!! Do not rename this param - just change type, but not the name of variable
     * @return Response
     */
    public function call(Request $request): Response
    {
        $response = new Response();
        $response->setTitle('Iphone 15');
        $response->setPrice(2000);
        return new Response();
    }
}
```