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

---

## Бросание ошибок из метода

Внутри `call()` можно бросить `JRPCException`, и бандл автоматически сформирует корректный JSON-RPC ответ с ошибкой:

```php
use OV\JsonRPCAPIBundle\Core\JRPCException;

#[JsonRPCAPI(methodName: 'deleteUser', type: 'POST')]
class DeleteUserMethod
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

Если из `call()` бросается любое исключение, не являющееся `JRPCException` (например, `RuntimeException`, `TypeError`), бандл всё равно перехватит его и сформирует JSON-RPC ответ с ошибкой. Код и сообщение будут взяты из оригинального исключения:

```json
{
    "jsonrpc": "2.0",
    "error": {
        "code": 0,
        "message": "Call to a member function getId() on null"
    },
    "id": "1"
}
```

> **Важно:** В production-окружении рекомендуется оборачивать бизнес-логику в `try/catch` и бросать `JRPCException` с понятными сообщениями, чтобы не раскрывать внутреннюю структуру приложения клиентам.
