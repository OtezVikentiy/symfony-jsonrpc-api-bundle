[English](validation.md) · [Русский](validation.ru.md)

# Parameter validation

The bundle validates incoming request parameters automatically, from the property types of the Request class. No extra configuration is needed.

---

## How it works

When a method is registered, the bundle analyses the Request class through Reflection and builds a set of validators from the property types:

```php
class Request
{
    private int $id;           // required, type int
    private string $title;     // required, type string
    private ?string $email;    // optional (nullable), type string
    private int $page = 1;     // optional (has a default), type int
}
```

When a request arrives, the parameters are checked by the Symfony Validator with an `Assert\Type` constraint. Since 5.0 the comparison is strict: `Assert\Type` looks at the actual PHP type of the decoded JSON value, with no coercion. `"42"` (a string) for an `int` field returns `-32602 Invalid params` rather than being cast quietly to a number — likewise `"true"` or `"1"` for a `bool` field, and so on. Previously PHP's weak typing could coerce a string to a number when the setter was called; now every point that depends on client input — the DTO constructor, setters, adders, nested DTOs — catches the `TypeError` and turns it into `-32602`, instead of falling through to `-32603 Internal error` or an unhandled exception in the log.

---

## Required versus optional parameters

A parameter is **optional** when:

- the type is nullable (`?string`, `?int`), or
- the property has a default value (`private int $page = 1`).

In every other case the parameter is **required**.

For optional parameters the bundle accepts a value of the right type, `null`, an empty string, or the parameter being absent.

---

## The shape of a validation error

When parameters fail validation, the bundle returns an error with code `-32602` (Invalid params):

**Request:**
```json
{
    "jsonrpc": "2.0",
    "method": "getProduct",
    "params": {"id": "not_a_number", "title": 12345},
    "id": "1"
}
```

**Response:**
```json
{
    "jsonrpc": "2.0",
    "error": {
        "code": -32602,
        "message": "Invalid params. Additional info: [id] - This value should be of type int.\n[title] - This value should be of type string."
    },
    "id": "1"
}
```

---

## Validating nested objects

When a property of the Request class is typed as another class, the bundle creates an instance through its setters and validates every field:

```php
class Filter
{
    private int $id;
    private string $title;
    private bool $finished;

    // getters and setters...
}

class Request
{
    private Filter $filter;

    // getter and setter...
}
```

**Request:**
```json
{
    "jsonrpc": "2.0",
    "method": "getFilteredData",
    "params": {
        "filter": {"id": 1, "title": "test", "finished": true}
    },
    "id": "1"
}
```

Sending an unexpected field inside `filter` returns an error:

```json
{
    "jsonrpc": "2.0",
    "error": {
        "code": -32602,
        "message": "Invalid params. Additional info: Parameters unknownField is not expected in request."
    },
    "id": "1"
}
```

---

## Binding getters, setters and adders to DTO fields

The bundle matches a request parameter to a method of the Request class **by the exact property name**, not by substring. For a property `$userId` it resolves the getter `getUserId()`/`isUserId()`, the setter `setUserId()`, and — where one exists — an adder whose name comes from the property name through `Symfony\Component\String\Inflector\EnglishInflector` (`$categories` → `addCategory()`; `$children` → `addChild()`). Binding used to go through `str_contains()`, which let `getUserId()` accidentally satisfy both `userId` and `id`: if a Request class held `id` and `userId` at once, the validator for `id` could end up checking the value of `userId`. From 5.0 that ambiguity is impossible: binding is strictly by name, and an unbound method simply does not resolve.

## Supported types

| PHP type | Validated as |
|----------|--------------|
| `int` | `int` |
| `string` | `string` |
| `float` | `float` |
| `bool` | `bool` |
| `array` | `array` |
| A class (`Filter`, `Address`, …) | Recursive validation through setters |

---

## Permitting extra fields (allowExtraFields)

By default the bundle rejects any parameter the Request class does not describe. A field in `params` with no matching property returns `-32602`.

That can be switched off in two ways.

### Globally

Add `allow_extra_fields: true` to the bundle's configuration:

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    allow_extra_fields: true
```

With the global setting on, **every** JSON-RPC method ignores extra fields in a request, and the per-method attribute setting is ignored.

### Per method

Add `allowExtraFields: true` to the `#[JsonRPCAPI]` attribute:

```php
#[JsonRPCAPI(
    methodName: 'updateProduct',
    type: 'POST',
    allowExtraFields: true,
)]
class UpdateProductMethod implements ApiMethodInterface
{
    public function call(UpdateProductRequest $request): UpdateProductResponse
    {
        // ...
    }
}
```

The attribute setting applies only while the global `allow_extra_fields` is `false` (its default).

### Precedence

| Global config | Method attribute | Result |
|---------------|------------------|--------|
| `false` (default) | `false` (default) | Extra fields **rejected** |
| `false` | `true` | Extra fields **permitted** for that method |
| `true` | `false` | Extra fields **permitted** (global wins) |
| `true` | `true` | Extra fields **permitted** |

Since 5.0 `allow_extra_fields` applies identically at any depth. The flag used to be checked once at the top level, while recursive hydration of nested DTOs rejected extra fields unconditionally.
