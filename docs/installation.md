[English](installation.md) · [Русский](installation.ru.md)

# Installing the bundle

---

## What this covers

Step-by-step installation and configuration of the OtezVikentiy JSON-RPC API bundle.

---

## 1. Install through Composer

```bash
composer require otezvikentiy/json-rpc-api
```

## 2. Register the bundle (not needed with Symfony Flex)

```php
<?php
// config/bundles.php
return [
    //...
    OV\JsonRPCAPIBundle\OVJsonRPCAPIBundle::class => ['all' => true],
];
```

## 3. Create the configuration files

### Routing

```yaml
# config/routes/ov_json_rpc_api.yaml
ov_json_rpc_api:
    resource: '@OVJsonRPCAPIBundle/config/routes/routes.yaml'
```

The bundle registers a single route, `/api/v{version}`, through which every JSON-RPC request is handled. The HTTP methods supported are POST, GET, PUT, PATCH, DELETE and OPTIONS.

### Bundle configuration

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    access_control_allow_origin_list:
        - 'https://app.example.com'
        - 'https://admin.example.com'
        - '*'
    strict_notifications: true # the default; false returns a response to a Notification too, when the result is non-empty
    swagger:
        api_v1:
            api_version: '1'
            base_path: '%env(string:OV_JSON_RPC_API_BASE_URL)%'
            base_path_description: 'Production server (uses live data)'
            test_path: '%env(string:OV_JSON_RPC_API_TEST_URL)%'
            test_path_description: 'Sandbox server (uses test data)'
            base_path_variables:
                - {name: 'subdomain', value: 'api'}
                - {name: 'domain', value: 'localhost'}
                - {name: 'port', value: '31081'}
            test_path_variables:
                - {name: 'domain', value: 'test'}
            auth_token_name: 'X-AUTH-TOKEN'
            auth_token_test_value: '%env(string:OV_JSON_RPC_API_AUTH_TOKEN)%' #leave empty in production
            info:
                title: 'Some awesome api title here'
                description: 'Some description about your api here would be appreciated if you like'
                terms_of_service_url: 'https://terms_of_service_url.test/url'
                contact:
                    name: 'John Doe'
                    url: 'https://john-doe.test'
                    email: 'john.doe@john-doe.test'
                license: 'MIT license'
                licenseUrl: 'https://john-doe.test/mit-license'
```

Note that `'*'` cannot be combined with specific origins — see [cors.md](./cors.md). The example above lists both for illustration; in a real configuration choose one or the other. Each entry must be a full origin — `scheme://host[:port]`, exactly as the browser sends it in the `Origin` header; a bare hostname such as `localhost` never matches anything.

#### The configuration keys

| Key | Description |
|-----|-------------|
| `access_control_allow_origin_list` | The list of permitted CORS origins. Use `'*'`, on its own, to permit all |
| `strict_notifications` | At `true` (the default) a Notification — a request without `id` — receives no response, strictly per JSON-RPC 2.0, even when `call()` threw. At `false` a response with `id: null` is returned when the result is non-empty |
| `swagger.*.api_version` | The API version number for Swagger generation |
| `swagger.*.base_path` | The production server URL |
| `swagger.*.base_path_description` | The production server's description in Swagger |
| `swagger.*.test_path` | The test server URL |
| `swagger.*.test_path_description` | The test server's description in Swagger |
| `swagger.*.base_path_variables` | Variables substituted into `base_path` |
| `swagger.*.test_path_variables` | Variables substituted into `test_path` |
| `swagger.*.auth_token_name` | The name of the HTTP header carrying the authorisation token. Optional — leave it out and the generated document carries no security scheme at all |
| `swagger.*.auth_token_test_value` | A test value for the token. **Currently unused** — the generator takes only `auth_token_name` from the authorisation block. The key is accepted and validated but affects nothing |
| `swagger.*.info` | Information about the API: title, description, contact, license |

> **Substituting variables into a path**
>
> `base_path` and `test_path` support variables in braces. `base_path` may be written as
> `{protocol}://{host}:{port}`, with the values supplied under `base_path_variables`:
> ```yaml
> base_path: '{protocol}://{host}:{port}'
> base_path_variables:
>       - {name: 'protocol', value: 'https'}
>       - {name: 'host', value: 'some.domain'}
>       - {name: 'port', value: '100500'}
> ```
> The resulting Swagger URL is `https://some.domain:100500/api/v1`.

### Environment variables

```dotenv
# .env
###> otezvikentiy/json-rpc-api ###
OV_JSON_RPC_API_SWAGGER_PATH=public/openapi/
OV_JSON_RPC_API_BASE_URL=http://localhost
OV_JSON_RPC_API_TEST_URL=http://localhost
OV_JSON_RPC_API_AUTH_TOKEN=2f1f6aee7d994528fde6e47a493cc097
###< otezvikentiy/json-rpc-api ###
```

| Variable | Description |
|----------|-------------|
| `OV_JSON_RPC_API_SWAGGER_PATH` | Where the Swagger YAML files are generated, relative to the project root |
| `OV_JSON_RPC_API_BASE_URL` | The production server URL for the Swagger documentation |
| `OV_JSON_RPC_API_TEST_URL` | The test server URL for the Swagger documentation |
| `OV_JSON_RPC_API_AUTH_TOKEN` | A test value for the authorisation token, shown in Swagger UI |

## 4. Verify the installation

Send a test request:

```bash
curl -X POST http://localhost/api/v1 \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc": "2.0", "method": "test", "params": {}, "id": 1}'
```

With no method named `test` registered, you get a well-formed JSON-RPC error response:

```json
{
    "jsonrpc": "2.0",
    "error": {
        "code": -32601,
        "message": "Method not found."
    },
    "id": 1
}
```

That means the bundle is installed and working.

> **Important:** the `Content-Type: application/json` header above is a requirement, not decoration. For requests with a body (POST/PUT/PATCH/DELETE) the bundle accepts only `application/json`; form-encoded (`application/x-www-form-urlencoded`) and `multipart/form-data` are rejected with `-32600 Invalid Request` before any attempt to read the body as a JSON-RPC payload. This closes a CSRF vector: form-encoded is a "simple request" under the CORS specification, and without the check a malicious HTML form on a third-party site could call your RPC methods as the logged-in user, using their cookies, with no preflight request.

## Next steps

- [A basic example of an API method](./examples/base.md)
- [Generating the Swagger documentation](./swagger/tags.md)
- [Configuring security](./security/roles.md)
