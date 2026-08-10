[English](array.md) · [Русский](array.ru.md)

# Swagger — describing arrays

---

## What it does

The `#[SwaggerArrayProperty]` attribute describes the element type of an array property for the Swagger documentation.

Without it, arrays appear in Swagger as a bare `array` with nothing said about their contents.

---

## Attribute parameters

| Parameter | Type | Default | Description |
|-----------|------|:-------:|-------------|
| `type` | string | — (required) | The element type of the array: a scalar type or a class FQCN |
| `ofClass` | bool | `false` | Set to `true` when `type` is a class name (FQCN) |

## Examples

### An array of scalars

For arrays of strings, numbers and other scalar types, name the type directly:

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

Swagger renders that as:
```yaml
errors:
  type: array
  items:
    type: string
```

### An array of objects

For arrays holding objects (DTOs), give the class FQCN and `ofClass: true`:

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

    // ... getters and setters ...
}
```

Swagger renders that as:
```yaml
products:
  type: array
  items:
    type: object
    $ref: '#/components/schemas/App.RPC.V1.GetProducts.Product'
```

The bundle analyses the `Product` class's properties on its own and creates a separate schema under `components/schemas`. The schema name is the full class name (`App\RPC\V1\GetProducts\Product`) with `\` replaced by `.`: `schemaNameFromClassName()` builds it from the whole FQCN rather than from the short class name. The verbosity is deliberate. Before 5.0 the schema was simply `Product`, and if a project held two different DTOs sharing a short name — a common case, with `App\RPC\V1\GetProduct\Response` and `App\RPC\V1\GetProducts\Response` both called `Response` — they collected into the same `components/schemas` entry and silently overwrote one another. A schema named after the full class name can never collide with another as new DTOs are added.

## Combining the attributes

`#[SwaggerArrayProperty]` and `#[SwaggerProperty]` can be used together on different properties of one class, as the example above shows.
