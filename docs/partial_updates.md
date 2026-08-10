[English](partial_updates.md) · [Русский](partial_updates.ru.md)

# Partial updates (JSON Merge Patch)

`OV\JsonRPCAPIBundle\Core\Request\PartialRequestInterface` is an opt-in contract that lets the service layer tell "this field was not sent in the payload" from "this field was sent as `null`". It exists for PATCH scenarios, where the client sends only the fields it changed and must be able to **clear** a field by sending `null`.

The semantics follow [RFC 7396 (JSON Merge Patch)](https://datatracker.ietf.org/doc/html/rfc7396).

---

## Why it is needed

A typical update method:

```php
public function call(UpdateUserRequest $request): Response
{
    $user = $this->userRepository->find($request->getId());

    if ($request->getEmail() !== null) {
        $user->setEmail($request->getEmail());
    }
    if ($request->getBio() !== null) {
        $user->setBio($request->getBio());
    }
    // ...
}
```

There is a problem hidden in there: `null` in the DTO means two things at once —

- "the client did not send this field", which is the property's default value; and
- "the client explicitly sent `null`", which the framework stores in the property after setting it.

The service cannot tell them apart, and as a result **clearing a field is impossible**: sending `{"email": null}` makes the service evaluate `null !== null` as `false` and skip the setter.

---

## The solution

The DTO implements `PartialRequestInterface`, and the framework tracks which fields actually arrived in the payload. The service then uses `wasProvided()`:

```php
public function call(UpdateUserRequest $request): Response
{
    $user = $this->userRepository->find($request->getId());

    if ($request->wasProvided('email')) {
        $user->setEmail($request->getEmail()); // null means clear it
    }
    if ($request->wasProvided('bio')) {
        $user->setBio($request->getBio());
    }
    // ...
}
```

---

## Using it

### Through the `PartialUpdateRequest` base class

The shortest route is to extend `PartialUpdateRequest`. It brings `toArray()` (from `JsonRpcRequest`), the `PartialRequestInterface` implementation and the `TracksProvidedFieldsTrait` in one step.

```php
namespace App\RPC\V1\UpdateUser;

use OV\JsonRPCAPIBundle\Core\Request\PartialUpdateRequest;

class Request extends PartialUpdateRequest
{
    private ?int $id = null;
    private ?string $email = null;
    private ?string $name = null;
    private ?string $bio = null;

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): void { $this->id = $id; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): void { $this->email = $email; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): void { $this->name = $name; }

    public function getBio(): ?string { return $this->bio; }
    public function setBio(?string $bio): void { $this->bio = $bio; }
}
```

### Through the interface plus the trait

When the base class is already taken, assemble it by hand:

```php
use OV\JsonRPCAPIBundle\Core\Request\PartialRequestInterface;
use OV\JsonRPCAPIBundle\Core\Request\TracksProvidedFieldsTrait;

class Request implements PartialRequestInterface
{
    use TracksProvidedFieldsTrait;

    // ... fields with getters and setters
}
```

### Your own implementation

The trait is not compulsory. `markProvided` / `wasProvided` / `getProvidedFields` can be implemented by hand when the storage needs particular logic.

---

## Payload semantics

| Payload | `wasProvided('email')` | `getEmail()` | What the service should do |
|---|---|---|---|
| `{"email": "new@x.com"}` | `true` | `"new@x.com"` | set the new value |
| `{"email": null}` | `true` | `null` | clear the field |
| `{}` (key absent) | `false` | `null` (the default) | leave the field alone |

That is exactly JSON Merge Patch (RFC 7396).

---

## When `markProvided` is NOT called

The framework calls `markProvided($name)` only when the key was **actually present** in the raw JSON-RPC payload. It is not called when:

1. **The key is absent but the method specification carries a `defaultValue`.** The DTO property is filled from the specification, but `wasProvided` returns `false` — the value came from the bundle, not from the client.
2. **The synthetic `params` parameter** is in play (used for bulk payloads such as `[42, 23]`).
3. **The DTO does not implement `PartialRequestInterface`.** Tracking is then off entirely, for backward compatibility.

---

## Nested DTOs

`PartialRequestInterface` works recursively for nested objects. If you have a DTO with a nested address object and both implement the interface, tracking covers both levels:

```php
class AddressDto implements PartialRequestInterface
{
    use TracksProvidedFieldsTrait;
    private ?string $city = null;
    private ?string $street = null;
    // ...
}

class UpdateUserRequest extends PartialUpdateRequest
{
    private ?AddressDto $address = null;
    // ...
}
```

For the payload `{"address": {"city": "Moscow"}}`:

```php
$request->wasProvided('address');                 // true
$request->getAddress()->wasProvided('city');      // true
$request->getAddress()->wasProvided('street');    // false
```

That matches the object-merge semantics of RFC 7396.

---

## Backward compatibility

- DTOs that do not implement `PartialRequestInterface` behave exactly as before 3.9. No behaviour and no public signature changed.
- The `instanceof` check short-circuits: for a DTO without the interface the framework makes no extra calls.
- No configuration flag is needed: opting in through the interface is itself the toggle, at the level of a single Request class.

---

## Edge cases

### Required fields

If a field must be required even in a PATCH scenario — `id`, to identify the record — use an ordinary `Required` constraint in the validation specification. `wasProvided` does not replace validation.

### Fields that must not be cleared

A user's password, for instance: the client may change it but not clear it. Put that logic in the service:

```php
if ($request->wasProvided('password') && $request->getPassword() !== null) {
    $user->setPassword($this->hasher->hash($request->getPassword()));
}
```

### Boolean fields

`false` is a valid value; do not confuse it with `null`. `wasProvided` distinguishes all four cases correctly (`true` / `false` / `null` / key absent).

### Collections

The convention:

- explicit `null` → the client wants the relations cleared, if the business logic permits it;
- `[]` → an empty collection, which is also clearing;
- an array of values → a full replacement;
- key absent → leave it alone.

### Audit logs

`getProvidedFields()` returns the list of fields actually sent — convenient for logging only the real changes.

---

## How the framework does it

`RequestHandler::hydrateRequest()` has two branches for obtaining a value:

1. `array_key_exists($name, $baseRequest->getParams())` — the key is in the payload. Only in this case is the field marked through `markProvided`.
2. `array_key_exists('defaultValue', $allParameter)` — a fallback to the specification's default. The field is NOT marked.

The symmetric logic lives in `prepareParametersFromClass()` for recursive hydration of nested DTOs.

The details are in the source of `RequestHandler.php` — the `hydrateRequest` and `prepareParametersFromClass` methods.
