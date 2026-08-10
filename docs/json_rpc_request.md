[English](json_rpc_request.md) · [Русский](json_rpc_request.ru.md)

# The JsonRpcRequest base class

`OV\JsonRPCAPIBundle\Core\Request\JsonRpcRequest` is an abstract class your Request classes may extend in order to gain a `toArray()` method.

---

## What it is for

`toArray()` converts the request object — nested objects and arrays included — into an associative array, recursively. That is useful when:

- the request data has to be handed to another service as an array;
- the contents of the request need logging;
- the request has to be serialised for a message queue.

---

## Example

```php
namespace App\RPC\V1\GetProduct;

use OV\JsonRPCAPIBundle\Core\Request\JsonRpcRequest;

class Request extends JsonRpcRequest
{
    private int $id;
    private string $title;

    public function getId(): int { return $this->id; }
    public function setId(int $id): void { $this->id = $id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): void { $this->title = $title; }
}
```

In use:

```php
public function call(Request $request): Response
{
    $data = $request->toArray();
    // ['id' => 1, 'title' => 'Iphone 15']

    $this->logger->info('Request received', $data);

    // ...
}
```

---

## Nested objects

`toArray()` walks nested objects recursively:

```php
class Filter
{
    private int $id;
    private string $title;
    // getters, setters...
}

class Request extends JsonRpcRequest
{
    private Filter $filter;
    // getter, setter...
}
```

```php
$request->toArray();
// ['filter' => ['id' => 1, 'title' => 'test']]
```

Objects implementing `DateTimeInterface` (`DateTime` and `DateTimeImmutable`) are not broken down into properties — they are formatted as an ISO 8601 string (`DATE_ATOM`). If a nested object has a `toArray()` of its own, it is called.

> **Changed in 5.0.** `toArray()` used to read properties straight through Reflection, so a private field with no getter reached the result as well — and the documentation recommends this method for logging a request, which means a password or an internal token went into the log. A field is now exported if the class exposes it: through a public getter (`getX()`, `isX()` or the bare `x()`) or by being public itself, exactly as in a response. A private one with no public getter is not exported. Mutual references between request DTOs used to overflow the stack and kill the worker; a `JRPCException` with code `-32603` is thrown instead, which means **`toArray()` can now throw** — bear that in mind if you call it inside a logging block.
>
> Response serialisation uses **the same** mechanism (a shared trait), with one difference: in a request, a nested object with its own `toArray()` decides its own shape; in a response it does not, and the getters decide. See [the note on getters in the basic example](./examples/base.md#response) and [upgrade-5.0.md](./upgrade-5.0.md).

---

## When to use it

| Situation | Recommendation |
|-----------|----------------|
| A simple Request with a few fields | Extending is not necessary |
| You need `toArray()` for logging or handing data on | Extend `JsonRpcRequest` |
| Complex nested structures | Extend `JsonRpcRequest` |

Extending `JsonRpcRequest` is **not required** — the bundle works with any Request class. It is a utility base class, offered for convenience.
