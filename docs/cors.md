# CORS

Бандл управляет CORS-заголовками через `HeadersPreparer`, который консумирует `access_control_allow_origin_list` и `cors_strict` из конфигурации.

## Поведение 4.0

### Wildcard

```yaml
ov_json_rpc_api:
    access_control_allow_origin_list: ['*']
```

Любой запрос получает `Access-Control-Allow-Origin: *`. Подходит для публичных read-only API.

⚠️ **Wildcard несовместим с credentials.** Если ваш API требует cookies или `Authorization` header, нельзя использовать `*` — браузеры запретят. См. секцию «Credentials» ниже.

### Whitelist matching

```yaml
ov_json_rpc_api:
    access_control_allow_origin_list:
        - 'https://app.example.com'
        - 'https://admin.example.com'
```

Бандл читает заголовок `Origin` из запроса и:
- Если он есть в whitelist'е — возвращает `Access-Control-Allow-Origin: <тот самый origin>` + `Vary: Origin`.
- Если нет — заголовок не отдаётся вообще (при `cors_strict: true`). Браузер отклонит cross-origin запрос.

`Vary: Origin` важен, потому что разные origin'ы получат разные ответы — кэш не должен их склеивать.

### Empty list

```yaml
access_control_allow_origin_list: []
```

Возвращается пустая строка `Access-Control-Allow-Origin: ''` — браузер не примет. Эквивалентно «CORS выключен». Используется для same-origin only API.

### Legacy mode (`cors_strict: false`)

Если whitelist непустой и нет матча — возвращается `Access-Control-Allow-Origin: <list joined by ", ">`. Это **невалидный** заголовок по CORS-спеку (только один origin или wildcard), но он мог быть в 3.x. Включайте только для обратной совместимости.

## Preflight (OPTIONS)

Бандл сейчас **не обрабатывает** preflight-запросы (`OPTIONS`-метод). Если ваш фронтенд использует:
- non-GET/POST методы (PUT/PATCH/DELETE)
- кастомные заголовки (`X-AUTH-TOKEN`, `Authorization` с не-Basic схемой)
- `Content-Type` отличный от `text/plain`, `application/x-www-form-urlencoded`, `multipart/form-data`

— то браузер шлёт preflight `OPTIONS`. Бандл вернёт 404 или 405, и CORS-запрос не пройдёт.

**Решение:** настройте preflight на уровне reverse-proxy (nginx/Apache) или используйте отдельный CORS-bundle Symfony (например, `nelmio/cors-bundle`), который будет работать в связке.

Пример nginx:

```nginx
location /api/ {
    if ($request_method = OPTIONS) {
        add_header Access-Control-Allow-Origin $http_origin always;
        add_header Vary Origin always;
        add_header Access-Control-Allow-Methods 'POST, GET, PUT, PATCH, DELETE, OPTIONS' always;
        add_header Access-Control-Allow-Headers 'Content-Type, X-AUTH-TOKEN' always;
        add_header Access-Control-Max-Age 86400 always;
        return 204;
    }
    proxy_pass http://php-fpm;
}
```

## Credentials

Если API требует cookies или `Authorization`-header:

1. **Нельзя использовать `['*']`** — это нарушит спек.
2. Whitelist должен содержать конкретные origin'ы.
3. На уровне фронтенда: `fetch(url, {credentials: 'include'})`.
4. Бандл сейчас **не отдаёт** заголовок `Access-Control-Allow-Credentials: true` автоматически. Добавьте через nginx/middleware или PostProcessor.

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

# Foreign origin (strict mode)
curl -i -X POST https://api.example.com/api/v1 \
    -H 'Origin: https://evil.com' \
    -H 'Content-Type: application/json' \
    -d '{"jsonrpc":"2.0","method":"ping","id":1}'
# -> No Access-Control-Allow-Origin header
```

## Связанное

- [security_hardening.md](./security_hardening.md) — параметр `cors_strict` в контексте остальных hardening-настроек
- [upgrade-4.0.md](./upgrade-4.0.md) — что изменилось vs 3.x
