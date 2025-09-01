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

Below is a trait that contains preprocessor functions.
This approach would be useful, for example, if you want to log endpoint calls
or perhaps send something to an email every time some methods are called. You just need to create a trait and use it in
all API methods where it is required.

```php
<?php
// src/RPC/RpcPreProcessorTrait.php

namespace App\RPC;

use Psr\Log\LoggerInterface;

trait RpcPreProcessorTrait
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ){
    }

    public function getPreProcessors(): array
    {
        return [
            static::class => ['log'],
        ];
    }

    public function log(string $processorClass, ?object $requestInstance = null) {
        $this->logger->emergency('TEST TEST TEST ');
    }
}
```
```php
<?php
// src/RPC/V1/GetProduct.php

namespace App\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use OV\JsonRPCAPIBundle\Core\PreProcessorInterface;
use App\RPC\V1\GetProduct\Request;
use App\RPC\V1\GetProduct\Response;
use App\RPC\RpcPreProcessorTrait;

#[JsonRPCAPI(methodName: 'getProduct', type: 'POST')]
class GetProductMethod implements PreProcessorInterface
{
    use RpcPreProcessorTrait;
    
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
The trait for creating post-processors works in a similar way. The only difference is that it is called after the main 
logic and an additional response from the called API method is passed to it. Example of a trait:
```php
<?php
// src/RPC/RpcPostProcessorTrait.php

namespace App\RPC;

use Psr\Log\LoggerInterface;

trait RpcPostProcessorTrait
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ){
    }

    public function getPostProcessors(): array
    {
        return [
            static::class => ['log'],
        ];
    }

    public function log(string $processorClass, ?object $requestInstance = null, ?OvResponseInterface $response = null) {
        $this->logger->emergency('TEST TEST TEST ');
    }
}
```