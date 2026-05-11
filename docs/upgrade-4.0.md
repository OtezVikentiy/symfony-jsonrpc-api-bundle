# Upgrade Guide: 3.x → 4.0

Версия 4.0 — security-hardened релиз. **BC-breaking** изменения сведены в один шаг для предсказуемости.

## TL;DR

```yaml
# config/packages/ov_json_rpc_api.yaml — добавьте секцию compatibility,
# если хотите сохранить поведение 3.x:
ov_json_rpc_api:
    strict_notifications: false
    expose_internal_errors: true   # ⚠️ только если ваше окружение этого требует
    cors_strict: false
    # лимиты можно поднять под ваш профиль нагрузки
```

После обновления зависимости запустите тесты — фактическое поведение часто оказывается совместимым без явных override'ов.

## BC-breaking changes

### 1. `strict_notifications` теперь `true` по умолчанию

**3.x:** notification (запрос без `id`) с непустым результатом возвращал ответ.
**4.0:** строго по [JSON-RPC 2.0 spec](https://www.jsonrpc.org/specification#notification) — notification **никогда** не получает ответ.

**Что сломается:**
- Клиенты, которые шлют notification и ждут ответ (некорректное поведение, но было возможно в 3.x).
- Тесты, которые проверяют ответ на notification.

**Что делать:**
- Если клиент действительно ждёт ответ — он не notification, добавьте `"id": <значение>` в payload.
- Если нужно временно сохранить старое поведение: `strict_notifications: false`.

### 2. `expose_internal_errors: false` по умолчанию

**3.x:** любое исключение в методе уходило клиенту с полным `message` (потенциальная утечка).
**4.0:** только `JRPCException` отдаётся как есть. Прочие `Throwable` заменяются на `Internal error.` (`code: -32603`), полная информация уходит в `LoggerInterface`.

**Что сломается:**
- Клиенты, которые парсили текст ошибок (антипаттерн, но было).
- Тесты, ожидающие конкретные `message` для не-JRPCException.

**Что делать:**
- Использовать `JRPCException` для всех ошибок, которые **должны** быть видны клиенту.
- В dev: `expose_internal_errors: true`.
- В prod: оставить `false`, читать логи через Monolog.

### 3. CORS multi-origin: исправлен баг

**3.x:** список из нескольких origin'ов конкатенировался: `Access-Control-Allow-Origin: https://a.com, https://b.com` — невалидный заголовок по CORS-спеку, браузеры игнорируют.
**4.0:** `HeadersPreparer` матчит `Origin` request-заголовок против whitelist'а и отдаёт ровно один. Добавляет `Vary: Origin`. Если match'а нет — заголовок не отдаётся (при `cors_strict: true`).

**Что сломается:**
- Setup'ы, где `['*']` использовался — продолжают работать (wildcard поведение сохранено).
- Setup'ы с одним origin — продолжают работать (одиночный origin теперь матчится).
- Если ваш фронт был на origin'е, не входящем в whitelist, но "как-то работал" из-за конкатенации — он перестанет работать.

**Что делать:**
- Убедитесь, что все production origin'ы перечислены в `access_control_allow_origin_list`.
- Для legacy откатить: `cors_strict: false`.

### 4. DoS-лимиты включены по умолчанию

**3.x:** не было лимитов на payload, batch, depth, array param.
**4.0:** строгие safe-default'ы:
- `max_payload_bytes: 1048576` (1 MiB)
- `max_json_depth: 64`
- `max_batch_size: 50`
- `max_dto_depth: 10`
- `max_array_param_size: 1000`

**Что сломается:**
- Endpoint'ы, принимающие большие документы (импорт CSV/Excel, batch > 50, vложенные DTO глубже 10).
- Bulk-операции через `addX()` с массивами > 1000.

**Что делать:**
- Поднять лимиты под конкретный профиль API. См. [security_hardening.md](./security_hardening.md).

### 5. `Parse error.` message теперь включает причину

**3.x:** `{"error": {"code": -32700, "message": "Parse error."}}`
**4.0:** `{"error": {"code": -32700, "message": "Parse error. Additional info: Syntax error"}}` (или другой текст от `JsonException`)

**Что сломается:** клиенты, парсящие точное соответствие `message === "Parse error."`.
**Что делать:** парсить `error.code === -32700`, что и должно делаться по спеку.

### 6. Невидимые сеттеры в Request DTO теперь отвергаются

**3.x:** `setSecret()` с visibility `private` мог быть вызван через dynamic-dispatch при гидратации.
**4.0:** только публичные сеттеры. Невидимые — `INVALID_PARAMS`.

**Что сломается:** если кто-то намеренно делал `private function setX` чтобы блокировать поле от клиента — теперь оно не сетится, но возвращается ошибка вместо игнорирования.
**Что делать:** удалите такие сеттеры (если поле клиенту не нужно — оно не должно появляться в DTO). Или сделайте `public`.

### 7. `HeadersPreparer` конструктор принимает `RequestStack`

Для большинства пользователей это прозрачно (Symfony DI прокидывает `RequestStack` автоматически). Если вы инстанциируете `HeadersPreparer` руками (в тестах) — добавьте опциональный второй аргумент:

```php
new HeadersPreparer(['*']);                                   // ok, RequestStack = null
new HeadersPreparer(['https://a.com'], $stack);               // ok, для матчинга
new HeadersPreparer(['https://a.com'], $stack, corsStrict: false); // legacy fallback
```

### 8. `SwaggerGenerate::__construct(?string $name = null)`

Тип параметра явно `?string` (раньше был `string $name = null`, неявно nullable). Это исправляет deprecation PHP 8.4. Если вы наследовались от команды — обновите signature.

## Что НЕ ломается

- `#[JsonRPCAPI]` атрибут — все параметры, поведение версионирования.
- `ApiMethodInterface` и сигнатура `call()`.
- `JRPCException` — все коды и API.
- `PartialRequestInterface`, `PartialUpdateRequest`, `TracksProvidedFieldsTrait`, `wasProvided()` семантика.
- Pre/Post-процессоры.
- Batch-протокол (если размер ≤ 50).
- Swagger-генерация.
- Все интерфейсы Response.

## Чек-лист апгрейда

1. `composer require otezvikentiy/json-rpc-api:^4.0`
2. Прогнать тесты. Большинство падений — про новый strict default или sanitization.
3. Проверить production logs первые 24 часа — не появилось ли неожиданных `Internal error.` (значит у вас в коде есть не-`JRPCException` throw, который раньше уходил клиенту).
4. Проверить CORS из браузера — особенно для multi-origin setup'ов.
5. Замерить нагрузку и подкрутить лимиты под нужды ([security_hardening.md](./security_hardening.md)).

## Откат

В критическом случае можно сделать «всё как в 3.x» одним конфигом:

```yaml
ov_json_rpc_api:
    strict_notifications: false
    expose_internal_errors: true
    cors_strict: false
    max_payload_bytes: 10485760
    max_json_depth: 512
    max_batch_size: 10000
    max_dto_depth: 100
    max_array_param_size: 100000
```

Так получается «4.0 со всеми поведением 3.x». Но это **не рекомендуется** в prod — security-фиксы потеряны.
