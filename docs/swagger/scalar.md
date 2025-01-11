# Swagger - scalar properties description

---

## Description

How to define default, format and example for scalar properties of response.
Below you'll see some examples.

---

```php
<?php
// src/RPC/V1/GetProduct/Response.php

namespace App\RPC\V1\GetProduct;

use OV\JsonRPCAPIBundle\Core\Annotation\SwaggerProperty;

class Response
{
    #[SwaggerProperty(default: true)]
    private bool $success;
    
    #[SwaggerProperty(format: '/^[A-Za-z0-9 ]+$/', example: 'iphone 12')]
    private string $title;
    
    #[SwaggerProperty(example: 100)]
    private int $price;
    
    #[SwaggerProperty(default: 13.00, example: 18.00)]
    private float $tax;

    public function __construct(bool $success = true)
    {
        $this->success = $success;
    }
    
    // ... other getters and setters ...
}
```