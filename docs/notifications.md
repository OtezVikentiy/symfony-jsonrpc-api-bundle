# Notification-запросы

## Что такое Notification

Согласно [спецификации JSON-RPC 2.0](https://www.jsonrpc.org/specification#notification), Notification — это запрос **без поля `id`**. Клиент отправляет запрос, но не ожидает ответа от сервера.

```json
{"jsonrpc": "2.0", "method": "notify_hello", "params": [7]}
```

Обратите внимание: поле `id` отсутствует полностью — это не то же самое, что `"id": null`.

---

## Настройка поведения

Поведение бандла при получении Notification контролируется параметром `strict_notifications`:

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    strict_notifications: false  # значение по умолчанию
```

### `strict_notifications: true` (строгий режим)

Полное соответствие спецификации JSON-RPC 2.0. Сервер **не возвращает ответ** на Notification, даже если метод `call()` вернул непустой результат.

**Запрос:**
```bash
curl -X POST http://localhost/api/v1 \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc": "2.0", "method": "notify_hello", "params": [7]}'
```

**Ответ:** Пустой (HTTP 200 с пустым телом).

### `strict_notifications: false` (режим по умолчанию)

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

Для production-окружений рекомендуется `strict_notifications: true` — это соответствует спецификации и снижает объём трафика. Режим `false` удобен на этапе разработки и отладки.
