[English](jwt_bundle.md) · [Русский](jwt_bundle.ru.md)

# JWT-аутентификация

---

## Описание

Для полноценной JWT-аутентификации рекомендуется использовать бандл `lexik/jwt-authentication-bundle`. Он протестирован в связке с данным бандлом и работает без дополнительных настроек.

---

## Ссылки

- [Документация Symfony](https://symfony.com/bundles/LexikJWTAuthenticationBundle/current/index.html)
- [GitHub репозиторий](https://github.com/lexik/LexikJWTAuthenticationBundle)

## Установка

```bash
composer require lexik/jwt-authentication-bundle
```

Установка и настройка выполняется по инструкции бандла `lexik/jwt-authentication-bundle`. Для серверных клиентов (backend-to-backend, curl, мобильные приложения) специальных настроек для совместной работы с `otezvikentiy/json-rpc-api` не требуется. Для браузерных клиентов на другом origin'е — см. раздел «CORS preflight» ниже, там требуется явная настройка.

## Пример конфигурации

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

После настройки JWT-токен передаётся в заголовке `Authorization: Bearer <token>`.

## Браузерные клиенты и CORS preflight

`Authorization` — не [CORS-safelisted заголовок](https://developer.mozilla.org/en-US/docs/Glossary/CORS-safelisted_request_header): браузер на cross-origin запросе с этим заголовком сначала отправит preflight `OPTIONS`. Бандл отвечает на preflight сам (см. [cors.md](../cors.ru.md)), но `Access-Control-Allow-Headers` по умолчанию содержит только `Content-Type` — если не добавить `Authorization` в `cors_allowed_headers`, preflight его не разрешит, и браузер заблокирует реальный запрос с JWT ещё до отправки на сервер:

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    cors_allowed_headers:
        - 'Content-Type'
        - 'Authorization'
```

Same-origin запросы (фронтенд и API на одном origin'е) preflight не вызывают — эта настройка нужна только когда клиент и API разнесены по origin'ам.
