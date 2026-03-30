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

Установка и настройка выполняется по инструкции бандла `lexik/jwt-authentication-bundle`.
Специальных настроек для совместной работы с `otezvikentiy/json-rpc-api` не требуется — всё работает из коробки.

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
