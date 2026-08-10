[English](notifications.md) · [Русский](notifications.ru.md)

# Notification-запросы

## Что такое Notification

Согласно [спецификации JSON-RPC 2.0](https://www.jsonrpc.org/specification#notification), Notification — это запрос **без поля `id`**. Клиент отправляет запрос, но не ожидает ответа от сервера.

```json
{"jsonrpc": "2.0", "method": "notify_hello", "params": [7]}
```

Обратите внимание: поле `id` отсутствует полностью — это не то же самое, что `"id": null`. Запрос с явным `"id": null` — это **полноценный запрос**, а не Notification: он всегда получает ответ (с `"id": null` в ответе), независимо от `strict_notifications`.

```json
{"jsonrpc": "2.0", "method": "subtract", "params": [42, 23], "id": null}
```

**Ответ (всегда, даже при `strict_notifications: true`):**
```json
{"jsonrpc": "2.0", "result": {"result": 19}, "id": null}
```

---

## Настройка поведения

Поведение бандла при получении Notification контролируется параметром `strict_notifications`:

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    strict_notifications: true  # значение по умолчанию
```

### `strict_notifications: true` (строгий режим, по умолчанию)

Полное соответствие спецификации JSON-RPC 2.0. Сервер **не возвращает ответ** на Notification, даже если метод `call()` вернул непустой результат — и даже если `call()` бросил исключение. Единственное исключение: если конверт запроса настолько повреждён, что бандл не может достоверно определить, был ли это Notification (например, невалидный JSON), диагностика всё равно отправляется — узнать, что отправитель ждал ответ, невозможно.

**Запрос:**
```bash
curl -X POST http://localhost/api/v1 \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc": "2.0", "method": "notify_hello", "params": [7]}'
```

**Ответ:** Пустой (HTTP 200 с пустым телом).

### `strict_notifications: false` (лояльный режим, нужно включать явно)

Более мягкий режим для удобства разработки. Если метод вернул непустой результат — ответ будет отправлен клиенту, даже если запрос был Notification (без `id`). В ответе `id` будет `null`.

**Запрос:**
```bash
curl -X POST http://localhost/api/v1 \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc": "2.0", "method": "subtract", "params": [42, 23]}'
```

**Ответ:**
```json
{
    "jsonrpc": "2.0",
    "result": {"result": 19},
    "id": null
}
```

---

## Notification в batch-запросах

В batch-запросе Notification-элементы обрабатываются, но не включаются в массив ответов:

**Запрос:**
```json
[
    {"jsonrpc": "2.0", "method": "sum", "params": [1, 2, 4], "id": "1"},
    {"jsonrpc": "2.0", "method": "notify_hello", "params": [7]},
    {"jsonrpc": "2.0", "method": "subtract", "params": [42, 23], "id": "2"}
]
```

**Ответ (при `strict_notifications: true`):**
```json
[
    {"jsonrpc": "2.0", "result": {"result": 7}, "id": "1"},
    {"jsonrpc": "2.0", "result": {"result": 19}, "id": "2"}
]
```

Элемент `notify_hello` выполнился, но в ответе отсутствует.

---

## Когда использовать

- **Логирование событий** — клиенту не нужен результат
- **Отправка уведомлений** — fire-and-forget
- **Обновление статистики** — фоновая операция без ожидания ответа

---

## Рекомендация

`strict_notifications: true` (дефолт) — это соответствует спецификации и снижает объём трафика, не меняйте его без веской причины. Режим `false` можно включить явно на этапе разработки и отладки, если удобно видеть ответ даже на Notification.
