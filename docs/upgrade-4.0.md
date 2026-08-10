[English](upgrade-4.0.md) · [Русский](upgrade-4.0.ru.md)

# Upgrade guide: 3.x → 4.0

> This document describes the historical 3.x → 4.0 migration. The `cors_strict` key mentioned throughout is **removed in 5.0** — see [docs/upgrade-5.0.md](./upgrade-5.0.md) if you are upgrading to the current release.

Version 4.0 is a security-hardening release. The **BC-breaking** changes are gathered into one step, for predictability.

## In short

```yaml
# config/packages/ov_json_rpc_api.yaml — add a compatibility section
# if you want to keep the 3.x behaviour:
ov_json_rpc_api:
    strict_notifications: false
    expose_internal_errors: true   # ⚠️ only if your environment demands it
    cors_strict: false
    # the limits can be raised to suit your load profile
```

After updating the dependency, run your tests — the actual behaviour often turns out to be compatible with no overrides at all.

## BC-breaking changes

### 1. `strict_notifications` now defaults to `true`

**3.x:** a notification (a request without `id`) with a non-empty result received a response.
**4.0:** strictly per the [JSON-RPC 2.0 spec](https://www.jsonrpc.org/specification#notification) — a notification **never** receives one.

**What breaks:**
- Clients that send a notification and wait for a reply (incorrect behaviour, but possible in 3.x).
- Tests that assert on the response to a notification.

**What to do:**
- If the client genuinely expects a reply, it is not a notification: add `"id": <value>` to the payload.
- To keep the old behaviour for now: `strict_notifications: false`.

### 2. `expose_internal_errors: false` by default

**3.x:** any exception in a method reached the client with its full `message` — a potential leak.
**4.0:** only a `JRPCException` is passed through as-is. Other `Throwable`s are replaced by `Internal error.` (`code: -32603`), and the full information goes to `LoggerInterface`.

**What breaks:**
- Clients that parsed error text (an antipattern, but it happened).
- Tests expecting particular `message` values for non-`JRPCException` failures.

**What to do:**
- Use `JRPCException` for every error that **should** be visible to the client.
- In development: `expose_internal_errors: true`.
- In production: leave it `false` and read the logs through Monolog.

### 3. CORS multi-origin: a bug fixed

**3.x:** a list of several origins was joined together: `Access-Control-Allow-Origin: https://a.com, https://b.com` — an invalid header under the CORS specification, which browsers ignore.
**4.0:** `HeadersPreparer` matches the request's `Origin` header against the whitelist and returns exactly one. It adds `Vary: Origin`. With no match, no header is sent (under `cors_strict: true`).

**What breaks:**
- Setups using `['*']` keep working — the wildcard behaviour is unchanged.
- Setups with a single origin keep working — a lone origin now matches.
- If your frontend sat on an origin outside the whitelist but "somehow worked" thanks to the joining, it stops working.

**What to do:**
- Make sure every production origin is listed in `access_control_allow_origin_list`.
- To roll back for legacy reasons: `cors_strict: false`.

### 4. DoS limits are on by default

**3.x:** no limits on payload, batch, depth or array parameters.
**4.0:** strict safe defaults:
- `max_payload_bytes: 1048576` (1 MiB)
- `max_json_depth: 64`
- `max_batch_size: 50`
- `max_dto_depth: 10`
- `max_array_param_size: 1000`

**What breaks:**
- Endpoints accepting large documents (CSV/Excel imports, batches over 50, DTOs nested deeper than 10).
- Bulk operations through `addX()` with arrays over 1000 elements.

**What to do:**
- Raise the limits to suit the API's profile. See [security_hardening.md](./security_hardening.md).

### 5. The `Parse error.` message now carries the reason

**3.x:** `{"error": {"code": -32700, "message": "Parse error."}}`
**4.0:** `{"error": {"code": -32700, "message": "Parse error. Additional info: Syntax error"}}` (or other text from `JsonException`)

**What breaks:** clients matching `message === "Parse error."` exactly.
**What to do:** match on `error.code === -32700`, which is what the specification calls for anyway.

### 6. Non-public setters in request DTOs are now rejected

**3.x:** a `setSecret()` with `private` visibility could be called through dynamic dispatch during hydration.
**4.0:** public setters only. A non-public one gives `INVALID_PARAMS`.

**What breaks:** if someone deliberately made `private function setX` to keep a field away from clients, it is no longer set — but an error is returned rather than the field being ignored.
**What to do:** remove such setters (if the client has no business with the field, it should not be in the DTO at all), or make them `public`.

### 7. The `HeadersPreparer` constructor takes a `RequestStack`

For most users this is invisible — Symfony DI supplies the `RequestStack` automatically. If you construct `HeadersPreparer` by hand, in tests for instance, add the optional second argument:

```php
new HeadersPreparer(['*']);                                   // fine, RequestStack = null
new HeadersPreparer(['https://a.com'], $stack);               // fine, for matching
new HeadersPreparer(['https://a.com'], $stack, corsStrict: false); // legacy fallback
```

### 8. `SwaggerGenerate::__construct(?string $name = null)`

The parameter type is explicitly `?string` (it used to be `string $name = null`, implicitly nullable), which fixes a PHP 8.4 deprecation. If you extended the command, update the signature.

## What does NOT break

- The `#[JsonRPCAPI]` attribute — every parameter, and the versioning behaviour.
- `ApiMethodInterface` and the `call()` signature.
- `JRPCException` — every code and the whole API.
- `PartialRequestInterface`, `PartialUpdateRequest`, `TracksProvidedFieldsTrait`, and the `wasProvided()` semantics.
- Pre- and post-processors.
- The batch protocol, for sizes of 50 or fewer.
- Swagger generation.
- Every response interface.

## Upgrade checklist

1. `composer require otezvikentiy/json-rpc-api:^4.0`
2. Run the tests. Most failures will be about a new strict default or about sanitisation.
3. Watch the production logs for the first 24 hours — unexpected `Internal error.` responses mean your code throws something that is not a `JRPCException` and used to reach the client.
4. Check CORS from a browser, particularly for multi-origin setups.
5. Measure the load and tune the limits to fit ([security_hardening.md](./security_hardening.md)).

## Rolling back

In an emergency, one configuration block restores "everything as in 3.x":

```yaml
ov_json_rpc_api:
    strict_notifications: false
    expose_internal_errors: true
    cors_strict: false
    max_payload_bytes: 10485760
    max_json_depth: 512
    max_batch_size: 10000
    max_dto_depth: 100
    max_array_param_size: 100000
```

That gives you "4.0 with all of 3.x's behaviour". It is **not recommended** in production — the security fixes are gone with it.
