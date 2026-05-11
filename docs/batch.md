# Batch-запросы

Бандл поддерживает batch-формат JSON-RPC 2.0 — массив объектов-запросов в одном HTTP-запросе. Каждый элемент массива обрабатывается отдельно, и ответ — массив отдельных ответов.

## Базовый пример

```bash
curl -X POST http://localhost/api/v1 \
  -H "Content-Type: application/json" \
  -d '[
    {"jsonrpc": "2.0", "method": "sum",    "params": [1, 2, 4], "id": "1"},
    {"jsonrpc": "2.0", "method": "notify_hello", "params": [7]},
    {"jsonrpc": "2.0", "method": "subtract", "params": [42, 23], "id": "2"}
  ]'
```

Ответ:
```json
[
    {"jsonrpc": "2.0", "result": 7, "id": "1"},
    {"jsonrpc": "2.0", "result": 19, "id": "2"}
]
```

Запрос `notify_hello` — notification (без `id`) и в строгом режиме ответа не получает.

## Размер batch'а

С версии 4.0 действует лимит `max_batch_size` (default = 50). Превышение → весь batch отвергается одним ответом:

```json
{
    "jsonrpc": "2.0",
    "error": {
        "code": -32600,
        "message": "Invalid Request. Additional info: Batch size 51 exceeds limit 50."
    },
    "id": null
}
```

Зачем лимит: каждый запрос обрабатывается **последовательно** (нет внутреннего параллелизма). Без лимита один HTTP-запрос с 100 000 элементов мог занять минуты и съесть память — DoS-вектор. Подбирайте `max_batch_size` под:
- профиль ваших методов (если все методы быстрые — 100-200 ок; если кто-то тяжёлый — 10-20);
- SLA latency endpoint'а;
- бюджет памяти PHP-процесса.

## Порядок обработки

Запросы обрабатываются последовательно в порядке массива. Ответы возвращаются **в том же порядке**, в каком пришли запросы. Идентификация — через `id`-поле:

```json
[
    {"jsonrpc": "2.0", "method": "a", "id": 1},
    {"jsonrpc": "2.0", "method": "b", "id": 2}
]
```

Гарантируется ответ:
```json
[
    {"jsonrpc": "2.0", "result": ..., "id": 1},
    {"jsonrpc": "2.0", "result": ..., "id": 2}
]
```

## Batch со всеми notification

Если в batch'е каждый элемент — notification (без `id`), сервер **не должен** возвращать ничего. Бандл возвращает HTTP 200 с пустым telом.

```json
[
    {"jsonrpc": "2.0", "method": "log_event", "params": [...]},
    {"jsonrpc": "2.0", "method": "log_event", "params": [...]}
]
```
→ HTTP 200, body = `""`

## Mixed batch

Если есть и запросы, и notifications — ответ содержит только запросы.

```json
[
    {"jsonrpc": "2.0", "method": "sum", "params": [1,2], "id": 1},
    {"jsonrpc": "2.0", "method": "log_event"}
]
```
→ ответ содержит только результат `sum`, notification обрабатывается, но не возвращается.

## Ошибки в batch'е

Ошибка в одном запросе не прерывает обработку остальных. Каждый запрос-with-id получает свой response — успех или ошибку независимо:

```json
[
    {"jsonrpc": "2.0", "method": "nonexistent", "id": 1},
    {"jsonrpc": "2.0", "method": "sum", "params": [1,2], "id": 2}
]
```
→
```json
[
    {"jsonrpc": "2.0", "error": {"code": -32601, "message": "Method not found"}, "id": 1},
    {"jsonrpc": "2.0", "result": 3, "id": 2}
]
```

## Граничные случаи

### Пустой массив

```json
[]
```
→ `INVALID_REQUEST` (-32600). По спеку empty batch — невалидный запрос.

### Batch размера 1

```json
[{"jsonrpc": "2.0", "method": "sum", "id": 1}]
```
→ обрабатывается как single batch (Factory различает массив-of-arrays vs одиночный объект). Ответ — массив с одним элементом.

### Невалидный JSON

```
[{"jsonrpc": "2.0", "method": ...
```
→ `PARSE_ERROR` (-32700), весь batch отвергается.

## Транзакционность

Бандл **не предоставляет транзакционных гарантий** для batch'ей. Если запрос #3 упал, запросы #1 и #2 уже выполнены. Если нужен all-or-nothing — сделайте отдельный «бизнес-метод» который принимает массив операций и в случае неуспеха откатывает всё.

## Pre/Post-процессоры

Pre/post-процессоры вызываются для каждого запроса в batch'е независимо. Глобального процессора «на весь batch» нет.

## Связанное

- [security_hardening.md](./security_hardening.md) — `max_batch_size` и другие лимиты
- [notifications.md](./notifications.md) — strict vs lenient mode
- [errors.md](./errors.md) — коды ошибок, формат
