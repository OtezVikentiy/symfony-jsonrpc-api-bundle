# Troubleshooting / FAQ

Типичные проблемы и их решения.

---

## Method not found (-32601)

**Ошибка:**
```json
{"jsonrpc": "2.0", "error": {"code": -32601, "message": "Method not found."}, "id": "1"}
```

**Возможные причины:**

### 1. Класс не реализует `ApiMethodInterface`

Это самая частая причина. Бандл регистрирует только сервисы, помеченные тегом `ov.rpc.method` (`CompilerPass::process()` перебирает `$container->findTaggedServiceIds('ov.rpc.method')`). Этот тег вешается автоматически через `#[AutoconfigureTag('ov.rpc.method')]` на самом интерфейсе `ApiMethodInterface` — но только если класс метода **реализует** этот интерфейс. Атрибут `#[JsonRPCAPI]` сам по себе ничего не регистрирует, он лишь описывает метаданные уже зарегистрированного класса.

```php
use OV\JsonRPCAPIBundle\Core\ApiMethodInterface;
use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;

#[JsonRPCAPI(methodName: 'getProduct', type: 'POST')]
class GetProductMethod implements ApiMethodInterface // <-- без этого класс не зарегистрируется
{
    public function call(Request $request): Response { /* ... */ }
}
```

Добавлять `_instanceof: OV\JsonRPCAPIBundle\Core\ApiMethodInterface: tags: ['ov.rpc.method']` в свой `config/services.yaml` **не нужно и не поможет** — эквивалентная конфигурация уже зарегистрирована самим бандлом (`OVJsonRPCAPIExtension::load()` вызывает `$container->registerForAutoconfiguration(ApiMethodInterface::class)->addTag('ov.rpc.method')`). Если класс не реализует интерфейс, лишняя запись в вашем `services.yaml` ничего не изменит.

### 2. Класс лежит вне сканируемой директории

Убедитесь, что класс метода лежит в директории, которую сканирует Symfony (обычно `src/`) — если он не найден автозагрузчиком, тег `ApiMethodInterface` применить не к чему.

### 3. Нет атрибута `#[JsonRPCAPI]`

Каждый зарегистрированный метод API должен быть помечен атрибутом — без него `CompilerPass` пропустит класс (`extractAttributeMetadata()` вернёт `null`), даже если интерфейс реализован:

```php
use OV\JsonRPCAPIBundle\Core\ApiMethodInterface;
use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;

#[JsonRPCAPI(methodName: 'getProduct', type: 'POST')]
class GetProductMethod implements ApiMethodInterface
{
    public function call(Request $request): Response { /* ... */ }
}
```

### 4. Неправильное имя метода в запросе

Имя метода в JSON-запросе (`"method": "getProduct"`) должно точно совпадать с `methodName` в атрибуте. Регистр имеет значение.

### 5. Несовпадение версии API

Запрос на `/api/v2`, а метод зарегистрирован для версии 1 (namespace `App\RPC\V1\...`). Либо измените URL, либо укажите `version: 2` в атрибуте.

### 6. Несовпадение HTTP-метода

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

## Access denied (-32000)

**Ответ:** HTTP 200 с обычным JSON-RPC error-объектом — **не** HTTP 403:
```json
{"jsonrpc": "2.0", "error": {"code": -32000, "message": "Access denied."}, "id": "1"}
```

**Причина:** У текущего пользователя нет ни одной из ролей, указанных в `roles` атрибута. `RequestHandler::checkRoles()` бросает `JRPCException` с кодом `SERVER_ERROR` (`-32000`), поэтому отказ по правам проходит через тот же путь, что и любая другая ошибка метода, и всегда возвращается как корректный JSON-RPC объект с правильным `id` — в том числе внутри batch-запроса. Если вы мониторите долю HTTP 403 как прокси для отказов по ролям — читайте `error.code === -32000` вместо HTTP-статуса.

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
- Кодировка UTF-8

> Заголовок `Content-Type: application/json` — это отдельная проверка, которая идёт **до** попытки распарсить тело. Если он не указан или указан неверно (например, `application/x-www-form-urlencoded` при отправке формой), бандл вернёт не `-32700 Parse error`, а `-32600 Invalid Request` с `additionalInfo: "Content-Type must be application/json."`, даже если тело содержит валидный JSON.
