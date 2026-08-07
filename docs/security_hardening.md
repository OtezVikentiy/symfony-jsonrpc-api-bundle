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

CORS всегда строгий начиная с 5.0 — ключ `cors_strict` удалён, легаси comma-joined режима больше не существует ни при каком значении (см. [docs/cors.md](./cors.md)). Заголовки, которые preflight разрешает клиенту слать, настраиваются ключом `cors_allowed_headers` (default `['Content-Type']`) — расширьте список, если ваш фронтенд шлёт кастомные заголовки авторизации (например, `X-AUTH-TOKEN`).

Дополнительно, без отдельного конфиг-ключа: с версии 5.0 запросы с телом (POST/PUT/PATCH/DELETE) обязаны иметь заголовок `Content-Type: application/json`. Form-encoded (`application/x-www-form-urlencoded`) и `multipart/form-data` отклоняются с `-32600 Invalid Request` до попытки прочитать тело. Это закрывает CSRF-вектор: form-encoded — «simple request» по CORS-спеке, и без этой проверки сторонняя HTML-форма могла вызывать RPC-методы от имени залогиненного пользователя без preflight. Это также закрывает обход `max_payload_bytes` — раньше проверка размера смотрела только на «сырое» тело запроса, которое PHP не заполняет для form-encoded данных.

> **Граница этой защиты: методы, объявленные как `#[JsonRPCAPI(type: 'GET')]`.** Проверка Content-Type применяется к запросам с телом. GET-запрос тела не имеет — payload берётся из query string, — поэтому на GET-методы она не распространяется и распространяться не может. Сторонняя страница может вызвать GET-метод вашего API через `<img>`, `<script>` или простую навигацию, с куками пользователя и без preflight. **Методы с `type: 'GET'` обязаны быть идемпотентными и не иметь побочных эффектов.** Всё, что изменяет состояние, объявляйте POST/PUT/PATCH/DELETE — тогда Content-Type-гейт работает.
>
> Смежное: заголовок `X-HTTP-Method-Override: GET` на POST-запросе Symfony учитывает без opt-in, и такой запрос уходит в GET-ветку — тело игнорируется, payload берётся из query string. Выигрыша это не даёт (заголовок не входит в CORS-safelist, значит требует preflight; а клиент, умеющий ставить заголовки, мог бы просто послать GET), но знать об этом стоит: формулировка «запрос с телом обязан иметь `application/json`» обходится в том смысле, что тело просто перестаёт читаться.

## Что включается «по умолчанию» в 4.0

Если установить бандл и не задать ни один из новых ключей, бандл работает в **production-safe** режиме: лимиты включены, ошибки санитизированы, CORS строгий (без опции ослабить), notifications strict.

Чтобы вернуть большую часть поведения 3.x (не рекомендуется; CORS-строгость с 5.0 отключить нельзя ни при каком конфиге):

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    strict_notifications: false
    expose_internal_errors: true
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

Поведение всегда строгое (начиная с 5.0 переключателя не существует):

- `access_control_allow_origin_list: ['*']` → `Access-Control-Allow-Origin: *` (без `Vary`).
- `access_control_allow_origin_list: ['https://a.com', 'https://b.com']` → если `Origin` заголовок запроса попадает в список, эхо origin'а + `Vary: Origin`. Иначе CORS-заголовок не отдаётся.

Это исправляет баг 3.x, где multi-origin список конкатенировался через `, `, что нарушает CORS-спек (только один origin или `*`). Легаси-режим 3.x (`cors_strict: false` в 4.x) с 5.0 удалён без замены.

С версии 5.0 бандл также сам отвечает на preflight (`OPTIONS`) — заголовки `Access-Control-Allow-Methods`, `Access-Control-Allow-Headers` (из `cors_allowed_headers`) и `Access-Control-Max-Age` формируются без стороннего CORS-бандла или reverse-proxy.

См. также [docs/cors.md](./cors.md).

## DoS-лимиты: на что обращать внимание

### `max_payload_bytes`

PHP читает тело HTTP-запроса в память до того, как бандл получит управление (см. `post_max_size` в `php.ini`). Бандл может только отвергнуть payload **после** его получения. Полная защита — комбинация:

1. `post_max_size`/`upload_max_filesize` в `php.ini` (системный лимит).
2. `client_max_body_size` в nginx или эквивалент в Apache.
3. `max_payload_bytes` бандла — финальная проверка перед декодированием.

Иначе говоря: **`max_payload_bytes` ограничивает обработку, а не буферизацию.** Он не даёт бандлу разбирать слишком большой документ, но к моменту проверки тело уже прочитано в память. Если вас беспокоит именно memory exhaustion — потолок задаётся пунктами 1 и 2, а не этим ключом.

### `max_json_depth`

`json_decode` нативно поддерживает параметр `$depth`. Бандл использует `JSON_THROW_ON_ERROR` и ловит `JsonException` → `PARSE_ERROR` (-32700).

**Асимметрия GET vs POST.** На POST-ветке `max_json_depth` действует полностью — вплоть до заявленного лимита (по умолчанию 64, максимум 512 по схеме конфигурации). На GET-ветке параметры приходят через query string, которую PHP сам разбирает в `$_GET` до того, как бандл получает управление, и глубина этого разбора ограничена директивой `max_input_nesting_level` в `php.ini` (по умолчанию **64**). Бандл проверяет `max_json_depth` и на GET-запросах тоже, но поднять эффективный лимит выше `max_input_nesting_level` конфигом бандла нельзя — PHP молча обрежет более глубокую структуру раньше, чем бандл её увидит. Если вашему API нужна глубина больше 64 для GET-запросов, поднимайте `max_input_nesting_level` в `php.ini` (или используйте POST для таких вызовов, где лимит бандла honours полностью).

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
- [ ] `access_control_allow_origin_list` — конкретные origin'ы, не `['*']` (если API не публичный read-only)
- [ ] `cors_allowed_headers` включает все кастомные заголовки, которые реально шлёт ваш фронтенд (иначе preflight не пропустит запрос)
- [ ] `max_payload_bytes` соответствует реальным нуждам, синхронизирован с nginx/php.ini
- [ ] LoggerInterface настроен и хранит логи в надёжном месте
- [ ] Rate-limiting на уровне middleware/reverse proxy (бандл сам по себе rate-limiting не делает)
- [ ] HTTPS only, HSTS включён
- [ ] Authentication (Symfony Security) настроена для методов с `roles`

## Дополнительно

- [docs/cors.md](./cors.md) — детальное поведение CORS, preflight, credentials
- [docs/upgrade-4.0.md](./upgrade-4.0.md) — миграция с 3.x на 4.0
- [docs/batch.md](./batch.md) — поведение batch-запросов
