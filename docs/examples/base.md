# Base example

---

## Description

This is a very base easiest example. Just to get to know how it works in base.

---

```php
<?php
// src/RPC/V1/GetProduct/Request.php

namespace App\RPC\V1\GetProduct;

class Request
{
    private int $id;
    private string $title;

    /** 
    * In order to make parameter id mandatory 
    * in request - we pass it to constructor 
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