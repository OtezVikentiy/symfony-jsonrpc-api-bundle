[English](jwt_bundle.md) · [Русский](jwt_bundle.ru.md)

# JWT authentication

---

## What this is

For full JWT authentication, `lexik/jwt-authentication-bundle` is the recommended choice. It has been tested alongside this bundle and works without extra configuration.

---

## Links

- [Symfony documentation](https://symfony.com/bundles/LexikJWTAuthenticationBundle/current/index.html)
- [GitHub repository](https://github.com/lexik/LexikJWTAuthenticationBundle)

## Installation

```bash
composer require lexik/jwt-authentication-bundle
```

Install and configure it by following that bundle's own instructions. For server-side clients — backend-to-backend, curl, mobile applications — nothing special is needed to make it work with `otezvikentiy/json-rpc-api`. For browser clients on a different origin, see "Browser clients and CORS preflight" below; that case does need explicit configuration.

## Example configuration

```yaml
# config/packages/lexik_jwt_authentication.yaml
lexik_jwt_authentication:
    secret_key: '%env(resolve:JWT_SECRET_KEY)%'
    public_key: '%env(resolve:JWT_PUBLIC_KEY)%'
    pass_phrase: '%env(JWT_PASSPHRASE)%'
    token_ttl: 3600
```

```yaml
# config/packages/security.yaml
security:
    firewalls:
        api:
            pattern: ^/api
            stateless: true
            jwt: ~
    access_control:
        - {path: ^/api/login, roles: PUBLIC_ACCESS}
        - {path: ^/api, roles: IS_AUTHENTICATED_FULLY}
```

Once configured, the JWT travels in the `Authorization: Bearer <token>` header.

## Browser clients and CORS preflight

`Authorization` is not a [CORS-safelisted header](https://developer.mozilla.org/en-US/docs/Glossary/CORS-safelisted_request_header): on a cross-origin request carrying it, the browser sends a preflight `OPTIONS` first. The bundle answers preflight itself (see [cors.md](../cors.md)), but `Access-Control-Allow-Headers` contains only `Content-Type` by default — without adding `Authorization` to `cors_allowed_headers`, the preflight will not permit it and the browser blocks the real request with the JWT before it ever reaches the server:

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    cors_allowed_headers:
        - 'Content-Type'
        - 'Authorization'
```

Same-origin requests — frontend and API on one origin — trigger no preflight; this setting is needed only when client and API live on different origins.
