# Swagger — описание скалярных свойств

---

## Описание

Атрибут `#[SwaggerProperty]` позволяет задать дополнительные метаданные для скалярных свойств ответа, которые будут отображаться в Swagger-документации.

---

## Параметры атрибута

| Параметр | Тип | Описание |
|----------|-----|----------|
| `default` | ?string | Значение по умолчанию, отображаемое в Swagger |
| `format` | ?string | Формат поля (например, `email`, `date-time`, regexp-паттерн) |
| `example` | ?string | Пример значения для Swagger UI |

Все параметры необязательны.

## Пример

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

    // ... getters и setters ...
}
```

## Результат в Swagger

Свойство `title` будет отображено со следующими метаданными:
- **format:** `/^[A-Za-z0-9 ]+$/`
- **example:** `iphone 12`

Свойство `tax` будет отображено с:
- **default:** `13.00`
- **example:** `18.00`

## Примечание

Атрибут `#[SwaggerProperty]` применяется только к **свойствам класса** (TARGET_PROPERTY) и влияет исключительно на генерацию Swagger-документации, не затрагивая логику обработки запросов.
