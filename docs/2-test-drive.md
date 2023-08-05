# Test-Drive

## Create directories and files

During the installation process, we defined the `src/RPC/V1/{*Method.php}` directory in the services and marked with
tags in it all the classes ending in `*Method.php` - these will be our API endpoints.

```
└── src
    └── RPC
        └── V1
            └── getProducts
                ├── getProductsRequest.php
                └── getProductsResponse.php
            └── getProductsMethod.php
```

Create the following classes:

```php
<?php

namespace App\RPC\V1\getProducts;

class GetProductsRequest
{
    private int $id;
    private string $title;

    /**
     * @param int $id
     */
    public function __construct(int $id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
```
```php
<?php

namespace App\RPC\V1\getProducts;

class GetProductsResponse
{
    private bool $success;
    private string $title;

    /**
     * @param string $title
     * @param bool $success
     */
    public function __construct(string $title, bool $success = true)
    {
        $this->success = $success;
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @param bool $success
     */
    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }
}
```
```php
<?php

namespace App\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use App\RPC\V1\getProducts\GetProductsRequest;
use App\RPC\V1\getProducts\GetProductsResponse;

/**
 * @JsonRPCAPI(methodName = "getProducts")
 */
#[JsonRPCAPI(methodName: 'getProducts')]
class GetProductsMethod
{
    /**
     * @param GetProductsRequest $request
     * @return GetProductsResponse
     */
    public function call(GetProductsRequest $request): GetProductsResponse
    {
        $a = 1;
        $b = 2;

        $id = $request->getId();
        return new GetProductsResponse($request->getTitle().'OLOLOLOLO');
    }
}
```
And now you can execute curl request like this:

```bash
curl --header "Content-Type: application/json" --request POST --data '{"jsonrpc": "2.0","method": "getProducts","params": {"title": "AZAZAZA"},"id": 1}' http://localhost/api/v1
```
And the answer will be something like this:

```bash
{"title":"AZAZAZA","success":true}
```
In total, in order to create a new endpoint for your RPC API, you only need to add 3 classes - this is the method itself and the folder with the request and response.




---

« [Installation](1-installation.md)