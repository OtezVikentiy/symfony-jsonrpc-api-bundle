# Swagger — описание массивов

---

## Описание

Атрибут `#[SwaggerArrayProperty]` позволяет описать тип элементов массива в свойствах ответа для Swagger-документации.

Без этого атрибута массивы в Swagger будут отображаться просто как `array` без информации о содержимом.

---

## Параметры атрибута

| Параметр | Тип | По умолчанию | Описание |
|----------|-----|:------------:|----------|
| `type` | string | — (обязательный) | Тип элементов массива (скалярный тип или FQCN класса) |
| `ofClass` | bool | `false` | Установите `true`, если `type` — это имя класса (FQCN) |

## Примеры

### Массив скалярных значений

Для массивов строк, чисел и других скалярных типов указывайте тип напрямую:

```php
use OV\JsonRPCAPIBundle\Core\Annotation\SwaggerArrayProperty;

class Response
{
    #[SwaggerArrayProperty(type: 'string')]
    private array $errors = [];

    #[SwaggerArrayProperty(type: 'integer')]
    private array $ids = [];
}
```

В Swagger это будет отображено как:
```yaml
errors:
  type: array
  items:
    type: string
```

### Массив объектов

Для массивов, содержащих объекты (DTO), укажите FQCN класса и `ofClass: true`:

```php
use OV\JsonRPCAPIBundle\Core\Annotation\SwaggerArrayProperty;
use OV\JsonRPCAPIBundle\Core\Annotation\SwaggerProperty;
use App\RPC\V1\GetProducts\Product;

class Response
{
    #[SwaggerProperty(default: true)]
    private bool $success;

    #[SwaggerArrayProperty(type: 'string')]
    private array $errors = [];

    #[SwaggerArrayProperty(type: Product::class, ofClass: true)]
    private array $products = [];

    #[SwaggerProperty(default: 0, example: 120)]
    private int $total;

    public function __construct(bool $success = true)
    {
        $this->success = $success;
    }

    // ... getters и setters ...
}
```

В Swagger это будет отображено как:
```yaml
products:
  type: array
  items:
    type: object
    $ref: '#/components/schemas/App_RPC_V1_GetProducts_Product'
```

Бандл автоматически проанализирует свойства класса `Product` и создаст отдельную схему в `components/schemas`.

## Комбинирование атрибутов

`#[SwaggerArrayProperty]` и `#[SwaggerProperty]` могут использоваться вместе на разных свойствах одного класса, как показано в примере выше.
