[English](security_hardening.md) · [Русский](security_hardening.ru.md)

# Security hardening

Version 4.0 introduced a set of configuration keys with production-safe defaults. This document explains what each one does, which attacks it closes, and how to tune the values for your load.

## Summary

| Key | 4.0 default | Attacks / risks | Raise it when | Lower it when |
|---|:-:|---|---|---|
| `max_payload_bytes` | `1048576` (1 MiB) | DoS through huge payloads, memory exhaustion | the API accepts large documents (CSV imports, base64 binaries) | the API only takes small commands — down to 65536 (64 KiB) |
| `max_json_depth` | `64` | DoS through deeply nested JSON (stack exhaustion, an attack on json_decode) | the API carries deeply nested structures (tree-shaped responses) | the API is flat — 16 or 32 |
| `max_batch_size` | `50` | Amplification: 1 HTTP request → N heavy operations | it is a batch API for importing data | it is a snappy real-time API — 10 |
| `max_dto_depth` | `10` | DoS through recursive DTOs → memory and stack | complex DTO hierarchies in one session | the contracts are simple — 5 |
| `max_array_param_size` | `1000` | DoS through arrays of tens of thousands of elements | bulk operations through `addX()` adders — 5000 and up | the API is narrow — 100 |
| `strict_notifications` | `true` | Departing from the spec surprises clients | (rarely needed) | only if your clients expect responses to notifications |
| `expose_internal_errors` | `false` | Leaking stack traces, file paths, database credentials | **never in production** | `true` in development and test environments only |

CORS is always strict from 5.0 onwards — the `cors_strict` key is gone, and the legacy comma-joined mode no longer exists at any setting (see [docs/cors.md](./cors.md)). Which headers the preflight permits a client to send is configured through `cors_allowed_headers` (default `['Content-Type']`); extend the list if your frontend sends custom authorisation headers such as `X-AUTH-TOKEN`.

One more thing, with no configuration key of its own: since 5.0 requests with a body (POST/PUT/PATCH/DELETE) must carry `Content-Type: application/json`. Form-encoded (`application/x-www-form-urlencoded`) and `multipart/form-data` are rejected with `-32600 Invalid Request` before any attempt to read the body. This closes a CSRF vector: form-encoded is a "simple request" under the CORS specification, and without the check a third-party HTML form could call RPC methods as the logged-in user with no preflight. It also closes a bypass of `max_payload_bytes` — the size check used to look only at the raw request body, which PHP does not populate for form-encoded data.

> **The one exception, and it is opt-in twice over: `multipart/form-data` file uploads.** With `multipart.enabled: true` *and* `acceptsMultipart: true` on a method, that method accepts multipart — and accepts, with it, the CSRF exposure described above, because `multipart/form-data` is a CORS "simple request" just as form-encoded is. Both switches are off by default, so nothing changes until someone turns them on deliberately, and a method that does not declare the flag is still refused with `-32600`. What to check before turning it on is in [docs/multipart.md](./multipart.md).

> **The boundary of this protection: methods declared `#[JsonRPCAPI(type: 'GET')]`.** The Content-Type check applies to requests with a body. A GET request has none — its payload comes from the query string — so the check does not, and cannot, extend to GET methods. A third-party page can call a GET method of your API through `<img>`, `<script>` or plain navigation, with the user's cookies and without preflight. **Methods with `type: 'GET'` must be idempotent and free of side effects.** Declare anything that changes state as POST/PUT/PATCH/DELETE, where the Content-Type gate does its work.
>
> Related: Symfony honours an `X-HTTP-Method-Override: GET` header on a POST request without any opt-in, and such a request takes the GET branch — the body is ignored and the payload comes from the query string. This gains an attacker nothing (the header is not CORS-safelisted, so it needs a preflight, and a client able to set headers could simply send a GET), but it is worth knowing: the rule "a request with a body must carry `application/json`" is circumvented in the sense that the body stops being read at all.

## What is on "by default" in 4.0

Install the bundle, set none of the new keys, and it runs in a **production-safe** mode: limits on, errors sanitised, CORS strict (with no option to loosen it), notifications strict.

To restore most of the 3.x behaviour — not recommended, and CORS strictness cannot be turned off at all from 5.0:

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    strict_notifications: false
    expose_internal_errors: true
    max_payload_bytes: 10485760    # 10 MiB
    max_batch_size: 1000
    max_dto_depth: 50
    max_array_param_size: 100000
```

## Error sanitisation

Under `expose_internal_errors: false` (the default) any `Throwable` other than `JRPCException` is replaced by:

```json
{"jsonrpc": "2.0", "error": {"code": -32603, "message": "Internal error."}, "id": <id>}
```

The complete original exception — trace, message, class name — goes to `Psr\Log\LoggerInterface`, if one is autowired:

```php
// src/Services/MyMethod.php
public function call(MyRequest $request): Response
{
    // throws \PDOException('connection failed: pass=secret')
    // -> the client sees "Internal error."; the log holds the full message
}
```

A `JRPCException` is always passed through as-is — those are messages the API author controls. Use `JRPCException` for every error that **should** be visible to the client.

## CORS

The behaviour is always strict; from 5.0 there is no switch:

- `access_control_allow_origin_list: ['*']` → `Access-Control-Allow-Origin: *` (no `Vary`).
- `access_control_allow_origin_list: ['https://a.com', 'https://b.com']` → when the request's `Origin` header is in the list, that origin is echoed back with `Vary: Origin`. Otherwise no CORS header is sent.

This fixes a 3.x bug where a multi-origin list was joined with `, `, which violates the CORS specification (one origin or `*`, never a list). The 3.x legacy mode (`cors_strict: false` in 4.x) is removed in 5.0 with no replacement.

Since 5.0 the bundle also answers preflight (`OPTIONS`) itself — `Access-Control-Allow-Methods`, `Access-Control-Allow-Headers` (from `cors_allowed_headers`) and `Access-Control-Max-Age` are assembled without a third-party CORS bundle or a reverse proxy.

See also [docs/cors.md](./cors.md).

## The DoS limits: what to watch

### `max_payload_bytes`

PHP reads the HTTP request body into memory before the bundle is given control (see `post_max_size` in `php.ini`). The bundle can only reject a payload **after** it has arrived. Full protection is a combination of:

1. `post_max_size` / `upload_max_filesize` in `php.ini` (the system limit);
2. `client_max_body_size` in nginx, or its Apache equivalent;
3. the bundle's `max_payload_bytes` — a final check before decoding.

Put differently: **`max_payload_bytes` bounds processing, not buffering.** It stops the bundle parsing an over-large document, but by the time the check runs the body is already in memory. If memory exhaustion is your concern, the ceiling is set by points 1 and 2, not by this key.

### `max_json_depth`

`json_decode` supports a `$depth` parameter natively. The bundle uses `JSON_THROW_ON_ERROR` and catches `JsonException` → `PARSE_ERROR` (-32700).

**An asymmetry between GET and POST.** On the POST branch `max_json_depth` applies in full, up to the declared limit (64 by default, 512 at most by the configuration schema). On the GET branch the parameters arrive through the query string, which PHP parses into `$_GET` before the bundle is given control, and the depth of that parse is bounded by `max_input_nesting_level` in `php.ini` (**64** by default). The bundle checks `max_json_depth` on GET requests too, but the effective limit cannot be raised above `max_input_nesting_level` from the bundle's configuration — PHP quietly truncates a deeper structure before the bundle ever sees it. If your API needs a depth above 64 for GET requests, raise `max_input_nesting_level` in `php.ini`, or use POST for those calls, where the bundle's limit is honoured in full.

### `max_batch_size`

Atomic. Exceeding it rejects the whole batch with a single `INVALID_REQUEST`. That is better than answering the first N and cutting off — the client gets an unambiguous signal.

### `max_dto_depth`

Checked at every level of recursion in `RequestHandler::prepareParametersFromClass()`. On exceeding it: `INVALID_PARAMS`, with the actual depth and the limit stated in `additionalInfo`.

### `max_array_param_size`

Applies only to parameters bound through `addX()` adders (`tokens` → `addToken(Token)`, for instance). It does not affect plain `array` fields with no adder.

## Logging

Wire `Psr\Log\LoggerInterface` into Symfony (Monolog by default) and the bundle uses it automatically to record sanitised exceptions:

```yaml
# config/packages/monolog.yaml
monolog:
    handlers:
        rpc_errors:
            type: stream
            path: '%kernel.logs_dir%/rpc_errors.log'
            level: error
            channels: ['app']
```

`ErrorSanitizer` logs the exception under the `exception` context key, which Monolog understands — the stack trace is formatted automatically.

## Production deployment checklist

- [ ] `expose_internal_errors: false`
- [ ] `strict_notifications: true`
- [ ] `access_control_allow_origin_list` names specific origins rather than `['*']` (unless the API is public and read-only)
- [ ] `cors_allowed_headers` includes every custom header your frontend actually sends (otherwise preflight blocks the request)
- [ ] `max_payload_bytes` matches real needs and is in step with nginx and `php.ini`
- [ ] `LoggerInterface` is configured and stores logs somewhere durable
- [ ] Rate limiting sits in middleware or the reverse proxy (the bundle does none of its own)
- [ ] HTTPS only, HSTS enabled
- [ ] Authentication (Symfony Security) is configured for methods carrying `roles`

## Further reading

- [docs/cors.md](./cors.md) — CORS behaviour in detail, preflight, credentials
- [docs/upgrade-4.0.md](./upgrade-4.0.md) — migrating from 3.x to 4.0
- [docs/batch.md](./batch.md) — how batch requests behave
