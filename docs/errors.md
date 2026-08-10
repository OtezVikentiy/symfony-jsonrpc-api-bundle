[English](errors.md) · [Русский](errors.ru.md)

# Error handling

The bundle implements the [JSON-RPC 2.0](https://www.jsonrpc.org/specification#error_object) error system in full.

---

## Error codes

| Code | Constant | When it occurs |
|------|----------|----------------|
| `-32700` | `JRPCException::PARSE_ERROR` | Invalid JSON in the request body |
| `-32600` | `JRPCException::INVALID_REQUEST` | `jsonrpc` or `method` missing, or the shape is wrong |
| `-32601` | `JRPCException::METHOD_NOT_FOUND` | No method is registered under that name |
| `-32602` | `JRPCException::INVALID_PARAMS` | Parameters failed type validation |
| `-32603` | `JRPCException::INTERNAL_ERROR` | An internal server error |
| `-32000` | `JRPCException::SERVER_ERROR` | A server error (the range -32000 to -32099 is reserved for these) |

---

## The shape of an error response

```json
{
    "jsonrpc": "2.0",
    "error": {
        "code": -32601,
        "message": "Method not found."
    },
    "id": "1"
}
```

When the `id` cannot be established — during a JSON parse failure, for instance — the response carries `"id": null`.

> **A known limitation:** an `id` above `PHP_INT_MAX` is not echoed back byte for byte. `json_decode()` turns such a number into a `float` and loses precision (`9223372036854775999` may come back as `9223372036854776000`). If your clients need large numeric ids intact, send the `id` as a string.

---

## Throwing errors from a method

Inside `call()` you may throw a `JRPCException`, and the bundle assembles a correct JSON-RPC error response from it:

```php
use OV\JsonRPCAPIBundle\Core\ApiMethodInterface;
use OV\JsonRPCAPIBundle\Core\JRPCException;

#[JsonRPCAPI(methodName: 'deleteUser', type: 'POST')]
class DeleteUserMethod implements ApiMethodInterface
{
    public function call(Request $request): Response
    {
        $user = $this->userRepository->find($request->getId());

        if ($user === null) {
            throw new JRPCException(
                'User not found.',
                JRPCException::INVALID_PARAMS
            );
        }

        // ...
    }
}
```

The response:

```json
{
    "jsonrpc": "2.0",
    "error": {
        "code": -32602,
        "message": "User not found."
    },
    "id": "1"
}
```

---

## Extra information in an error

The third constructor parameter of `JRPCException` is `additionalInfo`. It is appended to the message after `Additional info:`:

```php
throw new JRPCException(
    'Invalid params.',
    JRPCException::INVALID_PARAMS,
    'Field "email" must be a valid email address.'
);
```

The response:

```json
{
    "jsonrpc": "2.0",
    "error": {
        "code": -32602,
        "message": "Invalid params. Additional info: Field \"email\" must be a valid email address."
    },
    "id": "1"
}
```

---

## Server errors

The JSON-RPC 2.0 specification reserves codes from `-32000` to `-32099` for server errors. The bundle supports the whole range:

```php
throw new JRPCException(
    'Database connection failed.',
    -32001
);
```

A code outside the permitted ranges makes the `JRPCException` constructor throw an `Exception`.

---

## Unhandled exceptions

When `call()` throws anything that is not a `JRPCException` — a `RuntimeException`, a `TypeError`, a Doctrine failure — the bundle catches it and passes it through `OV\JsonRPCAPIBundle\Core\Services\ErrorSanitizer` **before** the response is assembled. Since 4.0 the sanitiser does precisely the opposite of what 3.x did: the code and message are **not** handed to the client as they are. The client receives a generic error, and the original exception, stack trace included, goes to `Psr\Log\LoggerInterface`:

```json
{
    "jsonrpc": "2.0",
    "error": {
        "code": -32603,
        "message": "Internal error."
    },
    "id": "1"
}
```

That is the production-safe default: `RuntimeException('DB password: ...')`, or a file path out of a `TypeError`, no longer reaches the client.

### `expose_internal_errors`

For local debugging the sanitiser can be switched off with `expose_internal_errors: true`, after which the original exception message reaches the client as-is — though not an arbitrary code: outside the permitted JSON-RPC ranges the code is still normalised to `-32603`:

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    expose_internal_errors: true # dev and test only, never production
```

A `JRPCException` thrown from your own code always passes through the sanitiser unchanged — it is the one exception type treated as part of a deliberate API contract rather than as internals leaking out.

> **Important:** `expose_internal_errors: false` (the default) is the last line of defence, not the only one. In production it is still worth wrapping business logic in `try/catch` and throwing a `JRPCException` with a meaningful message, so the client gets a useful diagnostic rather than a bare `Internal error.`
>
> More on limits and security configuration — [security_hardening.md](./security_hardening.md).
