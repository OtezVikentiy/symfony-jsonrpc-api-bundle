# CORS

Бандл управляет CORS-заголовками через `HeadersPreparer`, который консумирует `access_control_allow_origin_list` и `cors_allowed_headers` из конфигурации, и обрабатывает preflight (`OPTIONS`) самостоятельно, без внешних CORS-бандлов.

## Wildcard

```yaml
ov_json_rpc_api:
    access_control_allow_origin_list: ['*']
```

Любой запрос получает `Access-Control-Allow-Origin: *`. Подходит для публичных read-only API.

⚠️ **Wildcard несовместим с credentials.** Если ваш API требует cookies или `Authorization` header, нельзя использовать `*` — браузеры запретят. См. секцию «Credentials» ниже.

## Whitelist matching

```yaml
ov_json_rpc_api:
    access_control_allow_origin_list:
        - 'https://app.example.com'
        - 'https://admin.example.com'
```

Бандл читает заголовок `Origin` из запроса и:
- Если он есть в whitelist'е — возвращает `Access-Control-Allow-Origin: <тот самый origin>` + `Vary: Origin`.
- Если нет — `Access-Control-Allow-Origin` не отдаётся; браузер отклонит cross-origin запрос. `Vary: Origin` при этом всё равно отдаётся.

`Vary: Origin` важен, потому что разные origin'ы получат разные ответы — кэш не должен их склеивать. Отказ зависит от `Origin` ровно так же, как и разрешение, поэтому заголовок ставится в обоих случаях: иначе разделяемый кэш мог бы отдать безголовую версию ответа клиенту с разрешённым origin'ом или, наоборот, заголовок одного origin'а — другому.

**Смешивать `'*'` с конкретными origin'ами нельзя.** Wildcard проверяется первым и выигрывает, поэтому список вида `['https://app.example.com', '*']` отвечал бы `Access-Control-Allow-Origin: *` вообще всем — читается как whitelist, работает наоборот. С 5.0 такая конфигурация не компилируется: либо перечислите нужные origin'ы, либо используйте `'*'` в одиночку.

## Empty list

```yaml
access_control_allow_origin_list: []
```

CORS-заголовок не отдаётся вообще (ни для одного origin'а). Эквивалентно «CORS выключен». Используется для same-origin only API.

## Поведение всегда строгое

Начиная с 5.0 конфиг `cors_strict` **удалён** — легаси-режима с невалидным comma-joined заголовком (`Access-Control-Allow-Origin: https://a.com, https://b.com`) больше не существует ни при каком значении конфига. Поведение всегда как было при `cors_strict: true` в 4.x: origin вне whitelist'а не получает заголовок вообще. Если у вас в конфиге остался `cors_strict: false` (или `true`) — приложение **не запустится**: `Configuration` не объявляет `ignoreExtraKeys()`, поэтому неизвестный ключ приводит к `InvalidConfigurationException` при компиляции контейнера, а не к тихому игнорированию. Удалите строку `cors_strict` из вашего конфига при обновлении.

## Preflight (OPTIONS)

С версии 5.0 бандл **обрабатывает preflight-запросы сам**, без reverse-proxy или сторонних CORS-бандлов. Маршрут `/api/v{version}` принимает `OPTIONS` наравне с остальными методами; `ApiController::index()` перехватывает `OPTIONS` в самом начале, до JSON-RPC парсинга, и отвечает `204 No Content` с полным набором CORS-заголовков:

```bash
curl -i -X OPTIONS https://api.example.com/api/v1 \
    -H 'Origin: https://app.example.com' \
    -H 'Access-Control-Request-Method: POST'
```
```
HTTP/1.1 204 No Content
Access-Control-Allow-Origin: https://app.example.com
Vary: Origin
Access-Control-Allow-Methods: POST, GET, PUT, PATCH, DELETE
Access-Control-Allow-Headers: Content-Type
Access-Control-Max-Age: 86400
```

- `Access-Control-Allow-Methods` — список методов, реально объявленных на маршруте (POST, GET, PUT, PATCH, DELETE — без самого `OPTIONS`).
- `Access-Control-Allow-Headers` — берётся из конфига `cors_allowed_headers` (по умолчанию `['Content-Type']`), **не** отражает `Access-Control-Request-Headers` запроса обратно. Отражение запрошенных заголовков превратило бы проверку в формальность, разрешающую всё, что попросит клиент.
- `Access-Control-Max-Age: 86400` — браузер кэширует результат preflight на сутки.
- Origin-матчинг для preflight подчиняется тем же правилам, что и `prepareHeaders()` для обычных ответов (wildcard / whitelist / no match).
- Preflight никогда не долетает до JSON-RPC-парсинга — пустое тело `OPTIONS`-запроса не считается ошибкой `Invalid Request`.

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    cors_allowed_headers:
        - 'Content-Type'
        - 'X-AUTH-TOKEN'
```

Если ваш фронтенд шлёт кастомный заголовок авторизации (`X-AUTH-TOKEN`, нестандартный `Authorization`) — добавьте его в `cors_allowed_headers`, иначе браузер завалит preflight и реальный запрос не уйдёт.

Если у вас уже настроен preflight на уровне nginx/Apache или через `nelmio/cors-bundle` — с 5.0 это, скорее всего, стало избыточным дублированием; оставляйте внешнюю настройку только если вам нужно нестандартное поведение (например, `Access-Control-Allow-Credentials` — см. ниже).

## Credentials

Если API требует cookies или `Authorization`-header:

1. **Нельзя использовать `['*']`** — это нарушит спек.
2. Whitelist должен содержать конкретные origin'ы.
3. На уровне фронтенда: `fetch(url, {credentials: 'include'})`.
4. Бандл сейчас **не отдаёт** заголовок `Access-Control-Allow-Credentials: true` автоматически (ни для обычных ответов, ни для preflight). Добавьте через nginx/middleware или PostProcessor.

## Per-method CORS

Бандл применяет одинаковые CORS-заголовки для всех методов. Если нужны разные whitelist'ы для разных endpoint'ов — используйте отдельный CORS-bundle или роутинг на уровне reverse-proxy.

## Тестирование

```bash
# Allowed origin
curl -i -X POST https://api.example.com/api/v1 \
    -H 'Origin: https://app.example.com' \
    -H 'Content-Type: application/json' \
    -d '{"jsonrpc":"2.0","method":"ping","id":1}'
# -> Access-Control-Allow-Origin: https://app.example.com
# -> Vary: Origin

# Foreign origin
curl -i -X POST https://api.example.com/api/v1 \
    -H 'Origin: https://evil.com' \
    -H 'Content-Type: application/json' \
    -d '{"jsonrpc":"2.0","method":"ping","id":1}'
# -> No Access-Control-Allow-Origin header

# Preflight
curl -i -X OPTIONS https://api.example.com/api/v1 \
    -H 'Origin: https://app.example.com' \
    -H 'Access-Control-Request-Method: POST'
# -> 204, Access-Control-Allow-Origin + Allow-Methods + Allow-Headers + Max-Age
```

## Связанное

- [security_hardening.md](./security_hardening.md) — CORS в контексте остальных hardening-настроек
- [upgrade-5.0.md](./upgrade-5.0.md) — удаление `cors_strict`, появление `cors_allowed_headers` и preflight
- [upgrade-4.0.md](./upgrade-4.0.md) — историческое поведение 3.x → 4.0 (актуально только для чтения истории миграции; `cors_strict`, упомянутый там, в 5.0 удалён)
