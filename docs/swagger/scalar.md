[English](scalar.md) · [Русский](scalar.ru.md)

# Swagger — describing scalar properties

---

## What it does

The `#[SwaggerProperty]` attribute supplies extra metadata for scalar response properties, which then appears in the Swagger documentation.

---

## Attribute parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `default` | ?string | The default value shown in Swagger |
| `format` | ?string | The field's format (for example `email`, `date-time`, or a regexp pattern) |
| `example` | ?string | An example value for Swagger UI |

All parameters are optional.

## Example

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

    // ... getters and setters ...
}
```

## The result in Swagger

The `title` property is rendered with:
- **format:** `/^[A-Za-z0-9 ]+$/`
- **example:** `iphone 12`

The `tax` property with:
- **default:** `13.00`
- **example:** `18.00`

## Note

`#[SwaggerProperty]` applies to **class properties** only (TARGET_PROPERTY) and affects nothing but Swagger generation — request handling is untouched.
