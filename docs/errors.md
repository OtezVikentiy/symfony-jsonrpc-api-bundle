# Обработка ошибок

Бандл полностью реализует систему ошибок [JSON-RPC 2.0](https://www.jsonrpc.org/specification#error_object).

---

## Коды ошибок

| Код | Константа | Когда возникает |
|-----|-----------|----------------|
| `-32700` | `JRPCException::PARSE_ERROR` | Невалидный JSON в теле запроса |
| `-32600` | `JRPCException::INVALID_REQUEST` | Отсутствует `jsonrpc`, `method` или неверный формат |
| `-32601` | `JRPCException::METHOD_NOT_FOUND` | Метод с указанным именем не зарегистрирован |
| `-32602` | `JRPCException::INVALID_PARAMS` | Параметры не прошли валидацию типов |
| `-32603` | `JRPCException::INTERNAL_ERROR` | Внутренняя ошибка сервера |
| `-32000` | `JRPCException::SERVER_ERROR` | Серверная ошибка (зарезервировано: от -32000 до -32099) |

---

## Формат ошибки в ответе

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

Если `id` невозможно определить (например, при ошибке парсинга JSON), в ответе будет `"id": null`.

> **Известное ограничение:** `id`, превышающий `PHP_INT_MAX`, не возвращается байт-в-байт. `json_decode()` конвертирует такое число в `float`, теряя точность (например, `9223372036854775999` может вернуться как `9223372036854776000`). Если вашим клиентам нужны большие численные id без потерь — передавайте `id` строкой.

---

## Бросание ошибок из метода

Внутри `call()` можно бросить `JRPCException`, и бандл автоматически сформирует корректный JSON-RPC ответ с ошибкой:

```php
use OV\JsonRPCAPIBundle\Core\ApiMethodInterface;
use OV\JsonRPCAPIBundle\Core\JRPCException;

#[JsonRPCAPI(methodName: 'deleteUser', type: 'POST')]
class DeleteUserMethod implements ApiMethodInterface
{
    public function call(Request $request): Response
    {
        $user = $this->userRepository->find($request->getId());

        if ($user === null) {
            throw new JRPCException(
                'User not found.',
                JRPCException::INVALID_PARAMS
            );
        }

        // ...
    }
}
```

Ответ:

```json
{
    "jsonrpc": "2.0",
    "error": {
        "code": -32602,
        "message": "User not found."
    },
    "id": "1"
}
```

---

## Дополнительная информация в ошибке

Третий параметр конструктора `JRPCException` — `additionalInfo`. Он добавляется к сообщению через `Additional info:`:

```php
throw new JRPCException(
    'Invalid params.',
    JRPCException::INVALID_PARAMS,
    'Field "email" must be a valid email address.'
);
```

Ответ:

```json
{
    "jsonrpc": "2.0",
    "error": {
        "code": -32602,
        "message": "Invalid params. Additional info: Field \"email\" must be a valid email address."
    },
    "id": "1"
}
```

---

## Серверные ошибки (Server Error)

Спецификация JSON-RPC 2.0 резервирует диапазон кодов от `-32000` до `-32099` для серверных ошибок. Бандл поддерживает весь этот диапазон:

```php
throw new JRPCException(
    'Database connection failed.',
    -32001
);
```

Если передать код вне допустимых диапазонов, конструктор `JRPCException` бросит `Exception`.

---

## Необработанные исключения

Если из `call()` бросается любое исключение, не являющееся `JRPCException` (например, `RuntimeException`, `TypeError`, ошибка Doctrine), бандл перехватывает его и прогоняет через `OV\JsonRPCAPIBundle\Core\Services\ErrorSanitizer` **прежде** чем сформировать ответ. С версии 4.0 санитайзер делает ровно противоположное тому, что было в 3.x: код и сообщение клиенту **не** передаются как есть. Вместо этого клиент получает дженерик-ошибку, а оригинальное исключение (со stack trace) уходит в `Psr\Log\LoggerInterface`:

```json
{
    "jsonrpc": "2.0",
    "error": {
        "code": -32603,
        "message": "Internal error."
    },
    "id": "1"
}
```

Такое поведение — production-safe дефолт: `RuntimeException('DB password: ...')` или путь до файла из `TypeError` больше не долетают до клиента.

### `expose_internal_errors`

Для локальной отладки санитайзер можно отключить конфигом `expose_internal_errors: true` — тогда оригинальное сообщение исключения (но не произвольный код — вне допустимых JSON-RPC диапазонов код всё равно нормализуется в `-32603`) уходит клиенту как есть:

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    expose_internal_errors: true # только для dev/test, никогда в prod
```

`JRPCException`, брошенный из вашего кода, всегда проходит через санитайзер без изменений — это единственный тип исключения, который считается частью намеренного API-контракта, а не утечкой внутренностей.

> **Важно:** `expose_internal_errors: false` (дефолт) — это последняя линия защиты, а не единственная. В production-окружении по-прежнему рекомендуется оборачивать бизнес-логику в `try/catch` и бросать `JRPCException` с понятными сообщениями, чтобы клиент получал осмысленную диагностику вместо голого `Internal error.`.
>
> Подробнее о лимитах и security-конфигурации — [security_hardening.md](./security_hardening.md).
