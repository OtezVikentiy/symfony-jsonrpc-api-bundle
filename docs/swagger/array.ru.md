[English](array.md) · [Русский](array.ru.md)

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
    $ref: '#/components/schemas/App.RPC.V1.GetProducts.Product'
```

Бандл автоматически проанализирует свойства класса `Product` и создаст отдельную схему в `components/schemas`. Имя схемы — это полное имя класса (`App\RPC\V1\GetProducts\Product`) с `\` заменённым на `.`: `schemaNameFromClassName()` строит имя из FQCN целиком, а не из короткого имени класса. Это намеренно многословно: до 5.0 схема называлась просто `Product`, и если в проекте было два разных DTO с одинаковым коротким именем (частый случай — `App\RPC\V1\GetProduct\Response` и `App\RPC\V1\GetProducts\Response` оба называются `Response`), они собирались в одну и ту же запись `components/schemas`, тихо затирая друг друга. Схема, названная по полному имени класса, никогда не сталкивается с другой при добавлении нового DTO.

## Комбинирование атрибутов

`#[SwaggerArrayProperty]` и `#[SwaggerProperty]` могут использоваться вместе на разных свойствах одного класса, как показано в примере выше.
