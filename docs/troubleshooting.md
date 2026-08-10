[English](troubleshooting.md) · [Русский](troubleshooting.ru.md)

# Troubleshooting / FAQ

Common problems and what to do about them.

---

## Method not found (-32601)

**The error:**
```json
{"jsonrpc": "2.0", "error": {"code": -32601, "message": "Method not found."}, "id": "1"}
```

**Possible causes:**

### 1. The class does not implement `ApiMethodInterface`

This is by far the commonest cause. The bundle registers only services carrying the `ov.rpc.method` tag (`CompilerPass::process()` iterates `$container->findTaggedServiceIds('ov.rpc.method')`). That tag is applied automatically through `#[AutoconfigureTag('ov.rpc.method')]` on `ApiMethodInterface` itself — but only if the method class **implements** the interface. The `#[JsonRPCAPI]` attribute registers nothing on its own; it describes the metadata of a class that is already registered.

```php
use OV\JsonRPCAPIBundle\Core\ApiMethodInterface;
use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;

#[JsonRPCAPI(methodName: 'getProduct', type: 'POST')]
class GetProductMethod implements ApiMethodInterface // <-- without this the class is never registered
{
    public function call(Request $request): Response { /* ... */ }
}
```

Adding `_instanceof: OV\JsonRPCAPIBundle\Core\ApiMethodInterface: tags: ['ov.rpc.method']` to your own `config/services.yaml` is **neither necessary nor a fix** — the equivalent configuration is already registered by the bundle (`OVJsonRPCAPIExtension::load()` calls `$container->registerForAutoconfiguration(ApiMethodInterface::class)->addTag('ov.rpc.method')`). If the class does not implement the interface, an extra entry in your `services.yaml` changes nothing.

### 2. The class lives outside the scanned directory

Make sure the method class sits in a directory Symfony scans (usually `src/`). If the autoloader never finds it, there is nothing for the `ApiMethodInterface` tag to apply to.

### 3. No `#[JsonRPCAPI]` attribute

Every registered API method must carry the attribute; without it the `CompilerPass` skips the class (`extractAttributeMetadata()` returns `null`) even when the interface is implemented:

```php
use OV\JsonRPCAPIBundle\Core\ApiMethodInterface;
use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;

#[JsonRPCAPI(methodName: 'getProduct', type: 'POST')]
class GetProductMethod implements ApiMethodInterface
{
    public function call(Request $request): Response { /* ... */ }
}
```

### 4. The method name in the request is wrong

The method name in the JSON request (`"method": "getProduct"`) must match `methodName` in the attribute exactly. Case matters.

### 5. The API version does not match

The request goes to `/api/v2` while the method is registered for version 1 (namespace `App\RPC\V1\...`). Either change the URL or state `version: 2` in the attribute.

### 6. The HTTP method does not match

The request was sent with GET while the attribute says `type: 'POST'`. The request's HTTP method must match `type` in the attribute.

---

## Version is not defined

**The error:**
```
RuntimeException: Version for API endpoint ... is not defined.
Either use the version parameter in the JsonRPCAPI attribute explicitly,
or specify the API version number in the namespace, for example App\RPC\V1
```

**The cause:** the bundle could not derive the API version from the class's namespace.

**What to do:**

1. **Make sure the namespace contains `V{N}`:**
   ```
   App\RPC\V1\GetProductMethod           ← fine
   App\RPC\V1\Products\GetProductMethod  ← fine (nesting is supported)
   App\RPC\GetProductMethod              ← error, no V{N}
   ```

2. **Or state the version explicitly:**
   ```php
   #[JsonRPCAPI(methodName: 'getProduct', type: 'POST', version: 1)]
   ```

---

## Swagger is not generated

**An error from `bin/console ov:swagger:generate`:**

### 1. The environment variable is unset

Make sure `OV_JSON_RPC_API_SWAGGER_PATH` is set in `.env`:

```dotenv
OV_JSON_RPC_API_SWAGGER_PATH=public/openapi/
```

and that the directory exists:

```bash
mkdir -p public/openapi
```

### 2. There is no swagger configuration

`config/packages/ov_json_rpc_api.yaml` must carry a `swagger` section:

```yaml
ov_json_rpc_api:
    swagger:
        api_v1:
            api_version: '1'
            base_path: 'http://localhost'
            # ... the remaining keys
```

### 3. Every method is marked `ignoreInSwagger: true`

With every method excluded from Swagger, the file comes out empty.

---

## Access denied (-32000)

**The response:** HTTP 200 with an ordinary JSON-RPC error object — **not** HTTP 403:
```json
{"jsonrpc": "2.0", "error": {"code": -32000, "message": "Access denied."}, "id": "1"}
```

**The cause:** the current user holds none of the roles listed in the attribute's `roles`. `RequestHandler::checkRoles()` throws a `JRPCException` with the `SERVER_ERROR` code (`-32000`), so a permission refusal travels the same path as any other method error and always comes back as a well-formed JSON-RPC object with the right `id` — inside a batch request included. If you monitor the share of HTTP 403 responses as a proxy for role refusals, read `error.code === -32000` instead of the HTTP status.

**What to do:**

1. **Check the user's roles** — make sure the token or session carries the role you expect.

2. **Check the method's attribute:**
   ```php
   #[JsonRPCAPI(methodName: 'deleteUser', type: 'POST', roles: ['ROLE_ADMIN'])]
   ```
   Access is granted when the user holds **at least one** of the listed roles.

3. **Drop `roles` if you do not need the restriction** — by default a method is available to every authenticated user, or to everyone if no firewall is configured.

---

## Invalid params (-32602)

**The error:**
```json
{"jsonrpc": "2.0", "error": {"code": -32602, "message": "Invalid params. Additional info: ..."}, "id": "1"}
```

**The cause:** the types of the parameters sent do not match the property types of the Request class.

More on validation: [docs/validation.md](./validation.md)

---

## Parse error (-32700)

**The error:**
```json
{"jsonrpc": "2.0", "error": {"code": -32700, "message": "Parse error."}, "id": null}
```

**The cause:** the request body holds invalid JSON. Check:

- the JSON syntax — quotes, commas, brackets;
- that the encoding is UTF-8.

> The `Content-Type: application/json` header is a separate check that runs **before** any attempt to parse the body. If it is missing or wrong — `application/x-www-form-urlencoded` from a form submission, say — the bundle returns `-32600 Invalid Request` with `additionalInfo: "Content-Type must be application/json."` rather than `-32700 Parse error`, even when the body holds valid JSON.
