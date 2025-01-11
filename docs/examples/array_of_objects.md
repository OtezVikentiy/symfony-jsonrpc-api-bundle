# Example Array of objects in response

---

## Description

This example differs from the basic one only in that it returns not one object in the server response, but a
list of objects. This is often needed to return lists by filter. For example, products on the
product page in an online store.

---

```php
<?php
// src/RPC/V1/GetProducts/Request.php

namespace App\RPC\V1\GetProducts;

use OV\JsonRPCAPIBundle\Core\Request\JsonRpcRequest;

/**
 * If you want to easily get data as an array, you can extend this class
 */
class Request extends JsonRpcRequest
{
    private ?string $title = null;
    private ?int $priceFrom = null;
    private ?int $priceTo = null;
    private ?array $categories = null;

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getPriceFrom(): ?int
    {
        return $this->priceFrom;
    }

    public function setPriceFrom(?int $priceFrom): void
    {
        $this->priceFrom = $priceFrom;
    }

    public function getPriceTo(): ?int
    {
        return $this->priceTo;
    }

    public function setPriceTo(?int $priceTo): void
    {
        $this->priceTo = $priceTo;
    }

    public function getCategories(): ?array
    {
        return $this->categories;
    }

    public function setCategories(?array $categories): void
    {
        $this->categories = $categories;
    }

    public function addCategory(int $category): void
    {
        $this->categories[] = $category;
    }
}
```

```php
<?php
// src/RPC/V1/GetProducts/Product.php

namespace App\RPC\V1\GetProducts;

class Product
{
    private string $title;
    private int $price;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): Product
    {
        $this->title = $title;

        return $this;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function setPrice(int $price): Product
    {
        $this->price = $price;

        return $this;
    }
}
```

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

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function setErrors(array $errors): Response
    {
        $this->errors = $errors;

        return $this;
    }

    public function addError(string $error): Response
    {
        $this->errors[] = $error;

        return $this;
    }

    public function getProductss(): array
    {
        return $this->products;
    }

    public function setProducts(array $products): Response
    {
        $this->products = $products;

        return $this;
    }

    public function addProduct(Product $product): Response
    {
        $this->products[] = $product;

        return $this;
    }
}
```

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

    /**
     * @param Request $request // !!!ATTENTION!!! Do not rename this param - just change type, but not the name of variable
     * @return Response
     */
    public function call(Request $request): Response
    {
        $filter = $request->toArray(); //This is exactly the method you get via JsonRpcRequest
        
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

The logic implemented in this example may seem redundant and strange, but it
has a completely justified purpose. When the logic of methods becomes complex or
entities in the DB become large - you may not want to give ALL the data
or you may want to transform the data format or request additional data by some
parameters. This approach will help you do all this without any problems.