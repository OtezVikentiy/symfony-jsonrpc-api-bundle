# Security Hardening

Версия 4.0 вводит набор конфигурационных ключей с production-safe дефолтами. Документ объясняет, что делает каждый ключ, какие атаки он закрывает, и как тюнить значения под нагрузку.

## Сводная таблица

| Ключ | Default 4.0 | Атаки/риски | Когда повышать | Когда понижать |
|---|:-:|---|---|---|
| `max_payload_bytes` | `1048576` (1 MiB) | DoS через гигантские payload'ы, memory exhaustion | API принимает большие документы (CSV-импорт, бинарные base64) | API только мелкие команды — снизить до 65536 (64 KiB) |
| `max_json_depth` | `64` | DoS через глубоко вложенный JSON (stack exhaustion, attack on json_decode) | API с deeply-nested структурами (древовидные ответы) | Плоские API — 16 или 32 |
| `max_batch_size` | `50` | Amplification: 1 HTTP → N тяжёлых операций | Batch-API для импорта данных | Snappy real-time API — 10 |
| `max_dto_depth` | `10` | DoS через рекурсивные DTO ↔ memory/stack | Сложные иерархии DTO в одной сессии | Простые контракты — 5 |
| `max_array_param_size` | `1000` | DoS через массивы с десятками тысяч элементов | Bulk-операции (`addX()` адеры) — 5000+ | Узкие API — 100 |
| `strict_notifications` | `true` | Отступление от спека → неожиданное поведение клиентов | (обычно не нужно) | Только если ваши клиенты ждут ответы на notifications |
| `expose_internal_errors` | `false` | Утечка стек-трейсов, путей файлов, кредов БД | **никогда в prod** | `true` только в dev/тестовом окружении |
| `cors_strict` | `true` | Невалидный `Access-Control-Allow-Origin` для multi-origin | (обычно не нужно) | Только для legacy backwards-compat |

## Что включается «по умолчанию» в 4.0

Если установить бандл и не задать ни один из новых ключей, бандл работает в **production-safe** режиме: лимиты включены, ошибки санитизированы, CORS строгий, notifications strict.

Чтобы вернуть поведение 3.x (не рекомендуется):

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    strict_notifications: false
    expose_internal_errors: true
    cors_strict: false
    max_payload_bytes: 10485760    # 10 MiB
    max_batch_size: 1000
    max_dto_depth: 50
    max_array_param_size: 100000
```

## Sanitization ошибок

При `expose_internal_errors: false` (дефолт) любой `Throwable` кроме `JRPCException` заменяется на:

```json
{"jsonrpc": "2.0", "error": {"code": -32603, "message": "Internal error."}, "id": <id>}
```

Полное исходное исключение (с trace, message, classname) уходит в `Psr\Log\LoggerInterface`, если он autowired:

```php
// src/Services/MyMethod.php
public function call(MyRequest $request): Response
{
    // throws \PDOException('connection failed: pass=secret')
    // -> клиент видит "Internal error.", в логах — полное сообщение
}
```

`JRPCException` всегда отдаётся как есть — это контролируемые автором API сообщения. Используйте `JRPCException` для всех ошибок, которые **должны** быть видны клиенту.

## CORS

При `cors_strict: true` (дефолт):

- `access_control_allow_origin_list: ['*']` → `Access-Control-Allow-Origin: *` (без `Vary`).
- `access_control_allow_origin_list: ['https://a.com', 'https://b.com']` → если `Origin` заголовок запроса попадает в список, эхо origin'а + `Vary: Origin`. Иначе CORS-заголовок не отдаётся.

Это исправляет баг 3.x, где multi-origin список конкатенировался через `, `, что нарушает CORS-спек (только один origin или `*`).

При `cors_strict: false` (legacy) поведение 3.x восстанавливается — список склеивается через `, `. Используйте только если вы знаете, что ваши клиенты как-то с этим работали.

См. также [docs/cors.md](./cors.md).

## DoS-лимиты: на что обращать внимание

### `max_payload_bytes`

PHP читает тело HTTP-запроса в память до того, как бандл получит управление (см. `post_max_size` в `php.ini`). Бандл может только отвергнуть payload **после** его получения. Полная защита — комбинация:

1. `post_max_size`/`upload_max_filesize` в `php.ini` (системный лимит).
2. `client_max_body_size` в nginx или эквивалент в Apache.
3. `max_payload_bytes` бандла — финальная проверка перед декодированием.

### `max_json_depth`

`json_decode` нативно поддерживает параметр `$depth`. Бандл использует `JSON_THROW_ON_ERROR` и ловит `JsonException` → `PARSE_ERROR` (-32700).

### `max_batch_size`

Atomic. Превышение → весь batch отвергается с единым `INVALID_REQUEST`. Это правильнее, чем отвечать на первые N и обрывать — клиент получает однозначный сигнал.

### `max_dto_depth`

Срабатывает на каждом уровне рекурсии в `RequestHandler::prepareParametersFromClass()`. При превышении — `INVALID_PARAMS` с указанием реальной глубины и лимита в `additionalInfo`.

### `max_array_param_size`

Срабатывает только для параметров, привязанных через `addX()`-адеры (например, `tokens` → `addToken(Token)`). На прямые поля типа `array` без адера не действует.

## Логирование

Подключите `Psr\Log\LoggerInterface` в Symfony (по умолчанию Monolog) — бандл автоматически использует его для записи sanitized exceptions:

```yaml
# config/packages/monolog.yaml
monolog:
    handlers:
        rpc_errors:
            type: stream
            path: '%kernel.logs_dir%/rpc_errors.log'
            level: error
            channels: ['app']
```

В `ErrorSanitizer` исключение логируется через context-key `exception`, что Monolog'у понятно — стек-трейс автоматически форматируется.

## Production deployment checklist

- [ ] `expose_internal_errors: false`
- [ ] `strict_notifications: true`
- [ ] `cors_strict: true`
- [ ] `access_control_allow_origin_list` — конкретные origin'ы, не `['*']` (если API не публичный read-only)
- [ ] `max_payload_bytes` соответствует реальным нуждам, синхронизирован с nginx/php.ini
- [ ] LoggerInterface настроен и хранит логи в надёжном месте
- [ ] Rate-limiting на уровне middleware/reverse proxy (бандл сам по себе rate-limiting не делает)
- [ ] HTTPS only, HSTS включён
- [ ] Authentication (Symfony Security) настроена для методов с `roles`

## Дополнительно

- [docs/cors.md](./cors.md) — детальное поведение CORS, preflight, credentials
- [docs/upgrade-4.0.md](./upgrade-4.0.md) — миграция с 3.x на 4.0
- [docs/batch.md](./batch.md) — поведение batch-запросов
