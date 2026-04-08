# Troubleshooting / FAQ

Типичные проблемы и их решения.

---

## Method not found (-32601)

**Ошибка:**
```json
{"jsonrpc": "2.0", "error": {"code": -32601, "message": "Method not found."}, "id": "1"}
```

**Возможные причины:**

### 1. Класс не обнаруживается автоматически

Убедитесь что класс метода лежит в директории, которая сканируется Symfony (обычно `src/`), и что в `config/services.yaml` есть:

```yaml
_instanceof:
    OV\JsonRPCAPIBundle\Core\ApiMethodInterface:
        tags: ['ov.rpc.method']
```

### 2. Нет атрибута `#[JsonRPCAPI]`

Каждый метод API должен быть помечен атрибутом:

```php
use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;

#[JsonRPCAPI(methodName: 'getProduct', type: 'POST')]
class GetProductMethod
{
    public function call(Request $request): Response { /* ... */ }
}
```

### 3. Неправильное имя метода в запросе

Имя метода в JSON-запросе (`"method": "getProduct"`) должно точно совпадать с `methodName` в атрибуте. Регистр имеет значение.

### 4. Несовпадение версии API

Запрос на `/api/v2`, а метод зарегистрирован для версии 1 (namespace `App\RPC\V1\...`). Либо измените URL, либо укажите `version: 2` в атрибуте.

### 5. Несовпадение HTTP-метода

Запрос отправлен через GET, а атрибут указывает `type: 'POST'`. HTTP-метод запроса должен совпадать с `type` в атрибуте.

---

## Version is not defined

**Ошибка:**
```
RuntimeException: Version for API endpoint ... is not defined.
Either use the version parameter in the JsonRPCAPI attribute explicitly,
or specify the API version number in the namespace, for example App\RPC\V1
```

**Причина:** Бандл не смог определить версию API из namespace класса.

**Решения:**

1. **Убедитесь что в namespace есть `V{N}`:**
   ```
   App\RPC\V1\GetProductMethod          ← OK
   App\RPC\V1\Products\GetProductMethod  ← OK (вложенность поддерживается)
   App\RPC\GetProductMethod              ← Ошибка! Нет V{N}
   ```

2. **Или укажите версию явно:**
   ```php
   #[JsonRPCAPI(methodName: 'getProduct', type: 'POST', version: 1)]
   ```

---

## Swagger не генерируется

**Ошибка при `bin/console ov:swagger:generate`:**

### 1. Не задана переменная окружения

Убедитесь что `OV_JSON_RPC_API_SWAGGER_PATH` задана в `.env`:

```dotenv
OV_JSON_RPC_API_SWAGGER_PATH=public/openapi/
```

И что директория существует:

```bash
mkdir -p public/openapi
```

### 2. Нет конфигурации swagger

В `config/packages/ov_json_rpc_api.yaml` должна быть секция `swagger`:

```yaml
ov_json_rpc_api:
    swagger:
        api_v1:
            api_version: '1'
            base_path: 'http://localhost'
            # ... остальные параметры
```

### 3. Все методы помечены `ignoreInSwagger: true`

Если все методы исключены из Swagger, файл будет пустым.

---

## 403 Access not allowed

**Ответ:**
```
HTTP 403 — "Access not allowed"
```

**Причина:** У текущего пользователя нет ни одной из ролей, указанных в `roles` атрибута.

**Решения:**

1. **Проверьте роли пользователя** — убедитесь что токен/сессия содержит нужную роль.

2. **Проверьте атрибут метода:**
   ```php
   #[JsonRPCAPI(methodName: 'deleteUser', type: 'POST', roles: ['ROLE_ADMIN'])]
   ```
   Доступ разрешён, если у пользователя есть **хотя бы одна** из перечисленных ролей.

3. **Уберите `roles` если ограничение не нужно** — по умолчанию метод доступен всем аутентифицированным пользователям (или всем, если firewall не настроен).

---

## Invalid params (-32602)

**Ошибка:**
```json
{"jsonrpc": "2.0", "error": {"code": -32602, "message": "Invalid params. Additional info: ..."}, "id": "1"}
```

**Причина:** Типы переданных параметров не совпадают с типами свойств Request-класса.

Подробнее о валидации: [docs/validation.md](./validation.md)

---

## Parse error (-32700)

**Ошибка:**
```json
{"jsonrpc": "2.0", "error": {"code": -32700, "message": "Parse error."}, "id": null}
```

**Причина:** Тело запроса содержит невалидный JSON. Проверьте:
- Правильность синтаксиса JSON (кавычки, запятые, скобки)
- Заголовок `Content-Type: application/json`
- Кодировка UTF-8
