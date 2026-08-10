[English](cors.md) · [Русский](cors.ru.md)

# CORS

The bundle manages CORS headers through `HeadersPreparer`, which consumes `access_control_allow_origin_list` and `cors_allowed_headers` from the configuration, and handles preflight (`OPTIONS`) itself, without an external CORS bundle.

## Wildcard

```yaml
ov_json_rpc_api:
    access_control_allow_origin_list: ['*']
```

Every request receives `Access-Control-Allow-Origin: *`. Suitable for public read-only APIs.

⚠️ **The wildcard is incompatible with credentials.** If your API requires cookies or an `Authorization` header, `*` cannot be used — browsers forbid the combination. See "Credentials" below.

## Whitelist matching

```yaml
ov_json_rpc_api:
    access_control_allow_origin_list:
        - 'https://app.example.com'
        - 'https://admin.example.com'
```

The bundle reads the `Origin` header from the request and:

- if it is in the whitelist, returns `Access-Control-Allow-Origin: <that exact origin>` plus `Vary: Origin`;
- if it is not, `Access-Control-Allow-Origin` is not sent and the browser rejects the cross-origin request. `Vary: Origin` is still sent.

`Vary: Origin` matters because different origins get different responses, and a cache must not conflate them. A refusal depends on `Origin` exactly as much as a permission does, which is why the header goes out in both cases: otherwise a shared cache could serve the header-less version to a client with an allowed origin, or one origin's header to another.

**Mixing `'*'` with specific origins is not allowed.** The wildcard is checked first and wins, so a list like `['https://app.example.com', '*']` would answer `Access-Control-Allow-Origin: *` to everyone — it reads as a whitelist and behaves as its opposite. Since 5.0 such a configuration does not compile: either enumerate the origins you want, or use `'*'` on its own.

## An empty list

```yaml
access_control_allow_origin_list: []
```

No CORS header is sent at all, for any origin. Equivalent to "CORS off". Used for same-origin-only APIs.

## The behaviour is always strict

Since 5.0 the `cors_strict` key is **removed** — the legacy mode with its invalid comma-joined header (`Access-Control-Allow-Origin: https://a.com, https://b.com`) no longer exists at any setting. Behaviour is always what `cors_strict: true` gave in 4.x: an origin outside the whitelist receives no header at all. If `cors_strict: false` (or `true`) is still in your configuration, the application **will not boot**: `Configuration` does not declare `ignoreExtraKeys()`, so an unknown key raises `InvalidConfigurationException` during container compilation rather than being ignored quietly. Remove the `cors_strict` line when you upgrade.

## Preflight (OPTIONS)

Since 5.0 the bundle **handles preflight requests itself**, with no reverse proxy and no third-party CORS bundle. The `/api/v{version}` route accepts `OPTIONS` alongside the other methods; `ApiController::index()` intercepts `OPTIONS` at the very start, before any JSON-RPC parsing, and answers `204 No Content` with the full set of CORS headers:

```bash
curl -i -X OPTIONS https://api.example.com/api/v1 \
    -H 'Origin: https://app.example.com' \
    -H 'Access-Control-Request-Method: POST'
```
```
HTTP/1.1 204 No Content
Access-Control-Allow-Origin: https://app.example.com
Vary: Origin
Access-Control-Allow-Methods: POST, GET, PUT, PATCH, DELETE
Access-Control-Allow-Headers: Content-Type
Access-Control-Max-Age: 86400
```

- `Access-Control-Allow-Methods` — the methods actually declared on the route (POST, GET, PUT, PATCH, DELETE, without `OPTIONS` itself).
- `Access-Control-Allow-Headers` — taken from the `cors_allowed_headers` key (default `['Content-Type']`). It does **not** reflect the request's `Access-Control-Request-Headers` back. Reflecting the requested headers would turn the check into a formality that permits whatever the client asks for.
- `Access-Control-Max-Age: 86400` — the browser caches the preflight result for a day.
- Origin matching for preflight follows the same rules as `prepareHeaders()` does for ordinary responses (wildcard / whitelist / no match).
- Preflight never reaches JSON-RPC parsing — the empty body of an `OPTIONS` request is not treated as an `Invalid Request`.

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    cors_allowed_headers:
        - 'Content-Type'
        - 'X-AUTH-TOKEN'
```

If your frontend sends a custom authorisation header (`X-AUTH-TOKEN`, a non-standard `Authorization`), add it to `cors_allowed_headers`; otherwise the browser fails the preflight and the real request never leaves.

If you already have preflight configured in nginx/Apache or through `nelmio/cors-bundle`, that has most likely become redundant duplication as of 5.0. Keep the external configuration only where you need behaviour the bundle does not offer — `Access-Control-Allow-Credentials`, for instance; see below.

## Credentials

If the API requires cookies or an `Authorization` header:

1. **`['*']` cannot be used** — it violates the specification.
2. The whitelist must name specific origins.
3. On the frontend: `fetch(url, {credentials: 'include'})`.
4. The bundle does **not** currently emit `Access-Control-Allow-Credentials: true` automatically, for ordinary responses or for preflight. Add it through nginx, middleware, or a PostProcessor.

## Per-method CORS

The bundle applies the same CORS headers to every method. If different endpoints need different whitelists, use a dedicated CORS bundle or routing at the reverse-proxy level.

## Testing

```bash
# Allowed origin
curl -i -X POST https://api.example.com/api/v1 \
    -H 'Origin: https://app.example.com' \
    -H 'Content-Type: application/json' \
    -d '{"jsonrpc":"2.0","method":"ping","id":1}'
# -> Access-Control-Allow-Origin: https://app.example.com
# -> Vary: Origin

# Foreign origin
curl -i -X POST https://api.example.com/api/v1 \
    -H 'Origin: https://evil.com' \
    -H 'Content-Type: application/json' \
    -d '{"jsonrpc":"2.0","method":"ping","id":1}'
# -> No Access-Control-Allow-Origin header

# Preflight
curl -i -X OPTIONS https://api.example.com/api/v1 \
    -H 'Origin: https://app.example.com' \
    -H 'Access-Control-Request-Method: POST'
# -> 204, Access-Control-Allow-Origin + Allow-Methods + Allow-Headers + Max-Age
```

## Related

- [security_hardening.md](./security_hardening.md) — CORS in the context of the other hardening settings
- [upgrade-5.0.md](./upgrade-5.0.md) — the removal of `cors_strict`, the arrival of `cors_allowed_headers` and preflight
- [upgrade-4.0.md](./upgrade-4.0.md) — the historical 3.x → 4.0 behaviour (of interest only for reading the migration history; the `cors_strict` mentioned there is gone in 5.0)
