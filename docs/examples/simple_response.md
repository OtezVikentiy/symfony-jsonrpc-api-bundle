# Example with "Simple" response

---

## Description

This example will clearly show how you can return from the API, for example, 
an image or some document. Let's assume that this method should return documentation 
for some technically complex product.

---

```php
<?php
// src/RPC/V1/GetProductDocument/Request.php

namespace App\RPC\V1\GetProductDocument;

class Request
{
    private int $id;

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
}
```

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
```php
<?php
// src/RPC/V1/GetProductDocumentMethod.php

namespace App\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use App\RPC\V1\GetProductDocument\Request;
use App\RPC\V1\GetProductDocument\PlainResponse;
use App\Repository\ProductRepository;

#[JsonRPCAPI(methodName: 'getProductDocument', type: 'POST')]
class GetProductDocumentMethod
{
    public function __construct(
        private readonly ProductRepository $productRepository
    ) {}
    
    /**
     * @param Request $request // !!!ATTENTION!!! Do not rename this param - just change type, but not the name of variable
     * @return Response
     */
    public function call(Request $request): PlainResponse
    {
        $product = $this->productRepository->find($request->getId());
        
        return new PlainResponse(
            content: $product->getDocumentContents(),
            headers: ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }
}
```