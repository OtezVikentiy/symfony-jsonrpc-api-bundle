# Swagger - array properties description

---

## Description

how to describe array properties of response.
Below you'll see some examples.

---

```php
<?php
// src/RPC/V1/GetProduct/Response.php

namespace App\RPC\V1\GetProduct;

use OV\JsonRPCAPIBundle\Core\Annotation\SwaggerArrayProperty;
use OV\JsonRPCAPIBundle\Core\Annotation\SwaggerProperty;
use App\RPC\V1\GetProducts\Product;

class Response
{
    #[SwaggerProperty(default: true)]
    private bool $success;
    
    #[SwaggerArrayProperty(type: 'string')]
    private array $errors;
    
    #[SwaggerArrayProperty(type: Product::class, ofClass: true)]
    private array $medicalContracts = [];
    
    #[SwaggerProperty(default: 0, example: 120)]
    private int $total;

    public function __construct(bool $success = true)
    {
        $this->success = $success;
    }
    
    // ... other getters and setters ...
}
```