# Установка бандла

---

## Описание

Пошаговая инструкция по установке и настройке бандла OtezVikentiy JSON-RPC API.

---

## 1. Установите бандл через Composer

```bash
composer require otezvikentiy/json-rpc-api
```

## 2. Подключите бандл (не требуется при использовании Symfony Flex)

```php
<?php
// config/bundles.php
return [
    //...
    OV\JsonRPCAPIBundle\OVJsonRPCAPIBundle::class => ['all' => true],
];
```

## 3. Создайте конфигурационные файлы

### Маршрутизация

```yaml
# config/routes/ov_json_rpc_api.yaml
ov_json_rpc_api:
    resource: '@OVJsonRPCAPIBundle/config/routes/routes.yaml'
```

Бандл зарегистрирует единый маршрут `/api/v{version}`, через который обрабатываются все JSON-RPC запросы.
Поддерживаются HTTP-методы: POST, GET, PUT, PATCH, DELETE.

### Конфигурация бандла

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    access_control_allow_origin_list:
        - localhost
        - api.localhost
        - '*'
    strict_notifications: false # true — строго по JSON-RPC 2.0 (Notification без ответа)
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
            auth_token_test_value: '%env(string:OV_JSON_RPC_API_AUTH_TOKEN)%' #для prod используйте пустое значение
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

#### Описание параметров конфигурации

| Параметр | Описание |
|----------|----------|
| `access_control_allow_origin_list` | Список разрешённых CORS-доменов. Используйте `'*'` для разрешения всех |
| `strict_notifications` | При `true` — Notification (запрос без `id`) не получает ответ (строго по JSON-RPC 2.0). При `false` (по умолчанию) — ответ возвращается, если результат непустой |
| `swagger.*.api_version` | Номер версии API для генерации Swagger |
| `swagger.*.base_path` | URL production-сервера |
| `swagger.*.base_path_description` | Описание production-сервера в Swagger |
| `swagger.*.test_path` | URL тестового сервера |
| `swagger.*.test_path_description` | Описание тестового сервера в Swagger |
| `swagger.*.base_path_variables` | Переменные для подстановки в `base_path` |
| `swagger.*.test_path_variables` | Переменные для подстановки в `test_path` |
| `swagger.*.auth_token_name` | Имя HTTP-заголовка для токена авторизации |
| `swagger.*.auth_token_test_value` | Тестовое значение токена (для Swagger UI) |
| `swagger.*.info` | Информация об API: title, description, contact, license |

> **Подстановка переменных в path**
>
> Параметры `base_path` и `test_path` поддерживают переменные в фигурных скобках.
> Например, `base_path` может быть задан как `{protocol}://{host}:{port}`, а в секции
> `base_path_variables` указываются значения для подстановки:
> ```yaml
> base_path: '{protocol}://{host}:{port}'
> base_path_variables:
>       - {name: 'protocol', value: 'https'}
>       - {name: 'host', value: 'some.domain'}
>       - {name: 'port', value: '100500'}
> ```
> В результате URL для Swagger будет: `https://some.domain:100500/api/v1`

### Переменные окружения

```dotenv
# .env
###> otezvikentiy/json-rpc-api ###
OV_JSON_RPC_API_SWAGGER_PATH=public/openapi/
OV_JSON_RPC_API_BASE_URL=http://localhost
OV_JSON_RPC_API_TEST_URL=http://localhost
OV_JSON_RPC_API_AUTH_TOKEN=2f1f6aee7d994528fde6e47a493cc097
###< otezvikentiy/json-rpc-api ###
```

| Переменная | Описание |
|------------|----------|
| `OV_JSON_RPC_API_SWAGGER_PATH` | Путь для генерации Swagger YAML-файлов (относительно корня проекта) |
| `OV_JSON_RPC_API_BASE_URL` | URL production-сервера для Swagger-документации |
| `OV_JSON_RPC_API_TEST_URL` | URL тестового сервера для Swagger-документации |
| `OV_JSON_RPC_API_AUTH_TOKEN` | Тестовое значение токена авторизации (отображается в Swagger UI) |

## 4. Проверьте установку

Отправьте тестовый запрос:

```bash
curl -X POST http://localhost/api/v1 \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc": "2.0", "method": "test", "params": {}, "id": 1}'
```

Если метод `test` не зарегистрирован, вы получите корректный JSON-RPC ответ с ошибкой:

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

Это означает, что бандл установлен и работает корректно.

## Следующие шаги

- [Базовый пример создания API-метода](./examples/base.md)
- [Генерация Swagger-документации](./swagger/tags.md)
- [Настройка безопасности](./security/roles.md)
