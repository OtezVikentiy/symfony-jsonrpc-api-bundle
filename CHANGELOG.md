# Changelog

Все значимые изменения в этом проекте документируются в этом файле.

Формат основан на [Keep a Changelog](https://keepachangelog.com/ru/1.0.0/).

---

## [5.0] - 2026-08-07

Спецификация-conformance релиз: постоянный набор тестов на соответствие [JSON-RPC 2.0 spec](https://www.jsonrpc.org/specification) нашёл семнадцать отклонений, шестнадцать из которых исправлены здесь. Подробный гайд по миграции — [docs/upgrade-5.0.md](./docs/upgrade-5.0.md).

### Добавлено
- **Permanent conformance suite** — двадцать девять тестов на разделы 4, 4.1, 4.2, 5, 5.1, 6 спецификации, включая все примеры из раздела Examples. Регресс против спека теперь падает в CI, а не проходит незамеченным.
- **CORS preflight (`OPTIONS`) обрабатывается бандлом** — маршрут `/api/v{version}` принимает `OPTIONS`, `ApiController` отвечает `204 No Content` с `Access-Control-Allow-Methods`, `Access-Control-Allow-Headers` и `Access-Control-Max-Age: 86400` до JSON-RPC-парсинга. Внешний CORS-бандл/reverse-proxy больше не обязателен.
- **`cors_allowed_headers`** (default `['Content-Type']`) — список заголовков, отражаемых в `Access-Control-Allow-Headers` preflight-ответа.
- **CI-матрица** — PHP 8.2/8.3/8.4 × Symfony 6.4/7.x, job с lowest-разрешёнными зависимостями, coverage-gate (90% минимум), `composer audit`/`composer validate --strict` еженедельно, non-blocking канарейка на Symfony 8.

### Изменено (BC-breaking)
- **Content-Type обязателен для запросов с телом.** POST/PUT/PATCH/DELETE без `Content-Type: application/json` — `-32600 Invalid Request`, даже если тело — валидный JSON. Form-encoded и multipart отклоняются на этом же основании: form-encoded — «simple request» по CORS-спеке, и без проверки сторонняя HTML-форма могла вызывать RPC-методы от имени залогиненного пользователя без preflight (CSRF). Заодно закрывает обход `max_payload_bytes`, который раньше проверял только «сырое» тело, а form-encoded PHP кладёт не туда.
- **Скалярные параметры больше не приводятся.** `"42"` для поля `int` — `-32602 Invalid params`, а не тихое приведение. Любой `TypeError`, всплывающий из-за клиентского ввода (конструктор DTO, сеттер, аддер, вложенный DTO), теперь превращается в `-32602` вместо `-32603`/необработанного исключения в логе.
- **Ответ сериализуется только через публичные геттеры.** `getFoo()` / `isFoo()` / `foo()` — единственный путь, которым поле response-класса попадает в JSON. Свойство без геттера в ответ не попадает, даже публичное. Раньше сериализация читала свойства напрямую через Reflection — приватное поле без геттера (пароль, внутренний токен) уходило клиенту, а поле, унаследованное от родительского класса, терялось (`ReflectionClass::getProperties()` не возвращает приватные свойства родителя).
- **Циклический граф объектов в ответе — `-32603`, а не падение воркера.** Двунаправленная связь (заказ → пользователь → его заказы) раньше приводила к segfault: ни ответа, ни лога, `ErrorSanitizer` не успевал сработать, потому что переполнение стека — не исключение.
- **`DateTimeInterface` сериализуется в ISO 8601.** И в ответе (`BaseResponse`, раньше вообще не имел спецобработки — `DateTime`/`DateTimeImmutable` разваливались на пустой массив внутренних свойств), и в `JsonRpcRequest::toArray()` (раньше распознавал только `DateTime`, не `DateTimeImmutable`).
- **Методы DTO привязываются к свойствам по точному имени**, не по подстроке. `getUserId()` больше не может случайно удовлетворить и `userId`, и `id`. Аддеры коллекций резолвятся по имени свойства через `Symfony\Component\String\Inflector\EnglishInflector` (`children` → `addChild`), а не отбрасыванием последней буквы (`children` → `childre`, молча ничего не добавляя).
- **`allow_extra_fields` теперь одинаково применяется на любой глубине.** Раньше флаг проверялся один раз на верхнем уровне, а рекурсивная гидратация вложенных DTO отклоняла лишние поля безусловно.
- **Batch определяется по форме контейнера**, а не по первому элементу. Любой непустой JSON-массив-список — batch, даже если часть элементов невалидна. Раньше невалидный первый элемент откатывал всю обработку к single-request режиму, и валидные вызовы дальше в массиве терялись без следа.
- **`{"id": null}` — полноценный запрос**, получает ответ с `"id": null`. Notification — это отсутствие поля `id`, а не `null`-значение; раньше `isset()` не различал эти случаи. Notification теперь не получает ответа вообще, включая ошибочный результат (кроме случая, когда конверт запроса настолько повреждён, что нельзя достоверно установить, был ли это notification).
- **`params: null` отклоняется** (`-32600`), а не тихо трактуется как «параметров нет» — та же `isset()`-ловушка, что и для `id`.
- **Отказ по ролям — `-32000` в JSON-RPC error-объекте, HTTP 200**, не HTTP 403 с голой строкой. Раньше внутри batch эта голая строка портила JSON-структуру элемента и теряла `id`.
- **Коды ошибок вне допустимых JSON-RPC диапазонов нормализуются в `-32603`.** Произвольный `Throwable` с кодом `0` (или любым другим вне стандартных значений и `-32000..-32099`) больше не долетает до клиента как есть, даже при `expose_internal_errors: true`.
- **Имена схем OpenAPI не сталкиваются.** Схема ответа — `{methodName}Response`, вложенные DTO — по полному имени класса (`\` → `.`). Раньше короткое имя класса (`Response`, `Request`) собирало все одноимённые DTO проекта в одну схему, тихо затирая предыдущую.
- **`cors_strict` удалён.** Поведение всегда как было при `cors_strict: true` в 4.x — легаси comma-joined режима не существует. Оставшийся в конфиге ключ `cors_strict` теперь ошибка компиляции контейнера, а не молчаливо игнорируемое значение.
- **Границы зависимостей в манифесте.** `composer.json` теперь объявляет верхние границы (`^6.4 || ^7.0` для Symfony-компонентов, `8.2.* || 8.3.* || 8.4.*` для PHP) вместо открытых `>=`. `doctrine/annotations` убран из require — ничего в `src/`/`config/` на него не полагалось.

### Исправлено
- Well-formed JSON, не являющийся Request-объектом (`"42"`, голая строка, `true`, `null`) — теперь `-32600 Invalid Request` по разделу 5.1 спека, а не `-32700 Parse error`. Невалидный JSON по-прежнему `-32700`.
- `ResponseService` теперь требует `ErrorSanitizer` явно в конструкторе — раньше тестовый харнесс мог собрать `ResponseService` без него, и тесты валидировали конфигурацию, никогда не существовавшую в проде.
- GET-ветка теперь применяет `max_payload_bytes` и `max_json_depth` — раньше оба лимита обходились переносом payload'а в query string. **Асимметрия:** на GET-ветке `max_json_depth` фактически ограничен `max_input_nesting_level` из `php.ini` (default 64) — PHP разбирает query string раньше, чем бандл получает управление, и обрезает более глубокие структуры сам. На POST-ветке лимит honours полностью.

### Известные ограничения
- `id`, превышающий `PHP_INT_MAX`, не возвращается байт-в-байт: `json_decode()` превращает его во `float` с потерей точности.

---

## [4.2] - 2026-05-19

### Добавлено
- **Pluggable PSR-3 logger** — два новых конфига `logging.logger_service` и `logging.call_logger_service` плюс bundle-scoped alias `ov_json_rpc_api.logger`. Позволяют подменять либо внутренний PSR-3 sink, который `JsonRpcCallLogger` использует для записи (`logger_service` / alias `ov_json_rpc_api.logger`), либо целиком реализацию `JsonRpcCallLoggerInterface` (`call_logger_service` / alias `JsonRpcCallLoggerInterface`). Alias, заданный вручную в проектном `services.yaml`, выигрывает у config-ключа и даже у `logging.enabled: false` kill-switch — Symfony DI мерджит проектный `services.yaml` в контейнер раньше, чем компилируется расширение бандла. Override-точка для `JsonRpcLogFormatterInterface` из 4.1 не изменена. Подробности и полная таблица precedence — [docs/logging.md](./docs/logging.md).

---

## [4.1] - 2026-05-14

### Добавлено
- **Request/Response logging** — опциональная подсистема, выключена по умолчанию. Конфиг `ov_json_rpc_api.logging.enabled: true` включает дефолтный логгер с форматом `Request: [method] {body} context_id: <uuid>` и парным `Response`. Поддерживает маскировку sensitive-полей по регексам на имена ключей JSON (`logging.masking.key_patterns`), кастомный форматтер (`JsonRpcLogFormatterInterface`), кастомный маскер (`SensitiveDataMaskerInterface`), кастомный генератор context_id (`ContextIdGeneratorInterface`). Подробности и примеры — [docs/logging.md](./docs/logging.md).

---

## [4.0] - 2026-05-11

Security-hardened релиз. Подробности миграции — [docs/upgrade-4.0.md](./docs/upgrade-4.0.md).

### Добавлено
- **Sanitization ошибок** — новый сервис `OV\JsonRPCAPIBundle\Core\Services\ErrorSanitizer`. Любой `Throwable` кроме `JRPCException` заменяется на дженерик `Internal error.` (`-32603`); полное исключение пишется в `Psr\Log\LoggerInterface`. Контролируется конфигом `expose_internal_errors` (default `false`).
- **DoS-лимиты** с safe-default'ами:
  - `max_payload_bytes` (1 MiB) — размер сырого тела HTTP-запроса.
  - `max_json_depth` (64) — глубина вложенности JSON.
  - `max_batch_size` (50) — число запросов в одном batch'е.
  - `max_dto_depth` (10) — глубина рекурсии при гидратации вложенных DTO.
  - `max_array_param_size` (1000) — число элементов массива-параметра через `addX()`.
- **CORS origin matching** — `HeadersPreparer` теперь принимает `RequestStack`, читает заголовок `Origin` и матчит против whitelist'а. Добавляется `Vary: Origin`. Конфиг `cors_strict` (default `true`).
- **Security regression test suite** — `tests/Security/`: `PayloadLimitTest`, `BatchSizeLimitTest`, `DtoHydrationLimitsTest`, `ArrayParamLimitTest`, `ErrorSanitizationTest`, `CorsMultiOriginTest`, `SwaggerGenerateSecurityTest`.
- **Новая документация:**
  - [docs/security_hardening.md](./docs/security_hardening.md) — все новые конфиги + tuning.
  - [docs/upgrade-4.0.md](./docs/upgrade-4.0.md) — миграция с 3.x.
  - [docs/cors.md](./docs/cors.md) — CORS-поведение.
  - [docs/batch.md](./docs/batch.md) — batch-семантика и лимиты.
  - [docs/testing.md](./docs/testing.md) — гайд по написанию тестов для RPC-методов.
- **Coverage tooling в `phpunit.xml.dist`** — `<source>`-блок для PHPUnit 10+ coverage reports.
- **Прямые unit-тесты** на ранее не покрытые классы: `OVJsonRPCAPIExtension`, `MethodSpec\RequestMetadata`, `MethodSpec\SwaggerMetadata`, `PartialUpdateRequest`, плюс расширенное покрытие HTTP method enforcement и `INTERNAL_ERROR` сценариев.
- **Английский README** — добавлена секция Testing, паритет с русским README.

### Изменено (BC-breaking)
- **`strict_notifications` default переключён в `true`** — соответствие JSON-RPC 2.0 спеку. Notifications (запросы без `id`) **никогда** не получают ответ. Для legacy: `strict_notifications: false`.
- **`expose_internal_errors` default `false`** — production-safe. Сырые сообщения исключений больше не утекают клиенту.
- **`cors_strict` default `true`** — multi-origin теперь матчит request `Origin`; для origin'а вне whitelist'а CORS-заголовок не отдаётся.
- **DoS-лимиты включены по умолчанию** — большие payload'ы, batch'и, deeply-nested DTO, огромные массивы теперь отвергаются с `INVALID_REQUEST` / `INVALID_PARAMS`. Тюнинг через конфиг.
- **Невидимые сеттеры в Request DTO отвергаются** — `RequestHandler::prepareParametersFromClass()` теперь требует `ReflectionMethod::isPublic()`. Private/protected setters больше не вызываются через dynamic dispatch.
- **`SwaggerGenerate::__construct($name)` теперь явно `?string`** — fix для PHP 8.4 implicit-nullable deprecation.
- **`HeadersPreparer::__construct(array, ?RequestStack, bool)`** — добавлены два опциональных аргумента. Существующий `new HeadersPreparer(['*'])` продолжает работать.
- **`ResponseService::__construct(HeadersPreparer, ?ErrorSanitizer)`** — добавлен опциональный второй аргумент.
- **Parse-error `message` теперь содержит причину** — `"Parse error. Additional info: Syntax error"` вместо `"Parse error."`. Парсите `code === -32700`, не текст.
- **`tests/Controller/AbstractTest` → `AbstractControllerTestCase`** — переименование для соответствия PHPUnit 10+ конвенции. Если вы наследовались — обновите имя класса.

### Исправлено
- **CORS multi-origin bug** — список из нескольких origin'ов больше не конкатенируется через `, ` (это нарушало CORS-спек). Теперь возвращается ровно один origin, совпавший с request `Origin`-заголовком.
- **PHP 8.4 deprecation в `SwaggerGenerate`** — параметр `$name` стал явно `?string`.
- **`SwaggerGenerate` path containment** — `realpath()` валидация целевой директории; при невалидном пути команда возвращает `FAILURE` с понятным сообщением.

### Security
- HIGH: DoS через unbounded payload size — закрыт `max_payload_bytes`.
- HIGH: DoS через unbounded batch — закрыт `max_batch_size`.
- HIGH: DoS через unbounded DTO nesting recursion — закрыт `max_dto_depth`.
- HIGH: DoS через unbounded array-param expansion — закрыт `max_array_param_size`.
- HIGH: Information disclosure через `Throwable::getMessage()` — закрыт `ErrorSanitizer`.
- MEDIUM: невалидный CORS-заголовок при multi-origin — исправлен origin matching'ом.
- LOW: hardening DTO hydration — private/protected setters не вызываются.

---

## [3.9] - 2026-05-07

### Добавлено
- **Поддержка JSON Merge Patch (RFC 7396) на уровне Request DTO** — введён opt-in контракт, позволяющий сервис-слою различать «поле не передано в payload» и «поле передано как `null`» (= очистить).
  - **Интерфейс `OV\JsonRPCAPIBundle\Core\Request\PartialRequestInterface`** — контракт с методами `markProvided(string)`, `wasProvided(string): bool`, `getProvidedFields(): array`.
  - **Трейт `OV\JsonRPCAPIBundle\Core\Request\TracksProvidedFieldsTrait`** — дефолтная реализация контракта.
  - **Базовый класс `OV\JsonRPCAPIBundle\Core\Request\PartialUpdateRequest`** — `extends JsonRpcRequest implements PartialRequestInterface; use TracksProvidedFieldsTrait;`. Удобный шорткат для PATCH-сценариев.
  - **`RequestHandler::hydrateRequest()`** — после успешного set-а свойства, если DTO реализует `PartialRequestInterface`, фреймворк вызывает `markProvided($name)` ТОЛЬКО когда ключ реально присутствовал в raw-payload. Для веток `defaultValue` и синтетического `params` — не вызывается.
  - **`RequestHandler::prepareParametersFromClass()`** — симметричная поддержка для рекурсивных вложенных DTO (RFC 7396 object-merge).
- **Тест `TracksProvidedFieldsTraitTest`** — юнит-тест контракта трейта.
- **Тест `PartialRequestHydrationTest`** — интеграционные тесты гидратации:
  - ключ присутствует со значением → `wasProvided` true;
  - ключ присутствует с `null` → `wasProvided` true (поле очищено);
  - ключ отсутствует → `wasProvided` false;
  - сработал `defaultValue` → `wasProvided` false;
  - DTO без интерфейса не задевается (BC-проверка);
  - вложенный DTO с интерфейсом корректно трекает поля.

### Обратная совместимость
- Полностью сохранена. DTO, не реализующие `PartialRequestInterface`, ведут себя ровно как в 3.8 — `instanceof`-проверка короткозамкнута, ноль накладных расходов.
- Никаких новых обязательных параметров в публичных API. Конфиг-флаг не нужен — opt-in через интерфейс.

---

## [3.8] - 2026-04-13

### Добавлено
- **Настройка `allowExtraFields` для отключения валидации дополнительных полей** — позволяет разрешить передачу параметров, не описанных в Request-классе, без ошибки `-32602`.
  - **Глобальная настройка** — параметр `allow_extra_fields: true` в конфигурации `ov_json_rpc_api.yaml` отключает проверку для всех методов.
  - **Настройка через атрибут** — параметр `allowExtraFields: true` в `#[JsonRPCAPI]` отключает проверку для конкретного метода. Работает только когда глобальная настройка `false` (по умолчанию). Глобальный конфиг всегда имеет приоритет.
- **Тест `DenyExtraFieldsDefaultTest`** — проверяет, что по умолчанию дополнительные поля отклоняются.
- **Тест `AllowExtraFieldsGlobalTest`** — проверяет работу глобальной настройки `allow_extra_fields: true`.
- **Тест `AllowExtraFieldsAttributeTest`** — проверяет работу `allowExtraFields: true` через атрибут метода.
- **Тест `GlobalOverridesAttributeTest`** — проверяет, что глобальный `allow_extra_fields: true` побеждает локальный `allowExtraFields: false`.

---

## [3.7] - 2026-04-09

### Исправлено
- **Устранено смешивание корневого `id` запроса и `params.id`** — ранее бизнес-параметр `id` внутри `params` мог быть подменён корневым JSON-RPC `id` (и наоборот). Теперь эти значения полностью изолированы: корневой `id` используется только для корреляции запрос-ответ, а `params.id` — только для бизнес-логики Request DTO.
  - `instantiateRequest()` — убрана специальная ветка, которая подставляла корневой `id` в конструктор Request DTO вместо `params.id`.
  - `hydrateRequest()` — убран блок `if ($name === 'id')`, который подменял значение из `params` fallback-ом на корневой `id`.
  - `processValidatorsForRequestInstance()` — убран мерж корневого `id` в данные валидации, который мог затирать `params.id`.

### Добавлено
- **Тест `ParamsIdAndRootIdDoNotConflictTest`** — проверяет, что при разных значениях корневого `id` и `params.id` в одиночном запросе ответ содержит корневой `id`, а бизнес-логика получает `params.id`.
- **Тест `BatchParamsIdAndRootIdDoNotConflictTest`** — аналогичная проверка для batch-запросов с несколькими элементами.

---

## [3.5] - 2026-04-09

### Исправлено
- **Определение версии из вложенных namespace** — метод в `App\RPC\V1\SubDirectory\` теперь корректно определяет версию 1. Ранее регулярное выражение требовало `V{N}` строго в конце namespace, что не работало при любой вложенности директорий.

### Изменено
- **Тестовые фикстуры перенесены из `src/RPC/` в `tests/Fixtures/RPC/`** — production-код бандла больше не содержит тестовых контроллеров. Namespace фикстур не изменился (`OV\JsonRPCAPIBundle\RPC\...`), загрузка через `autoload-dev`.
- **Приведение кода к стандарту PER-2** — исправлены пустые тела классов/интерфейсов, конструкторы, grouped use, константы, отсутствие фигурных скобок у `if`, пробелы в операторах.
- **Создан `phpunit.xml.dist`** — стандартная конфигурация PHPUnit с исключением `tests/Fixtures` из сканирования.

### Рефакторинг
- **`SwaggerSchemaBuilder`** — логика генерации Swagger-схем вынесена из CLI-команды `SwaggerGenerate` в отдельный сервис `OV\JsonRPCAPIBundle\Swagger\SwaggerSchemaBuilder`. Команда стала тонким адаптером.
- **Value-объекты `RequestMetadata` и `SwaggerMetadata`** — параметры `MethodSpec` сгруппированы в два value-объекта. Конструктор `MethodSpec` принимает 8 параметров вместо 19. Все старые getters сохранены через делегирование (обратная совместимость).
- **`CompilerPass::process()` разбит на методы** — `extractAttributeMetadata()`, `resolveVersion()`, `analyzeRequestClass()`, `detectPlainResponse()`, `detectProcessors()`.
- **`RequestHandler::processRequestClass()` разбит** на `instantiateRequest()` и `hydrateRequest()`.
- **`Schema::addPropertyWithRequired()`** — устранено 16 дублей паттерна `addProperty` + `if ($required) addRequired`.
- **`BatchStrategyFactory`** — упрощён до тернарного оператора, `self::` вместо полного имени класса.
- **Исправлены инвертированные имена переменных** — `$setterAndPropertyTypesAreEqual` (при `!==`) переименован в `$setterTypeMismatch`.
- **`MethodSpecCollection`** — тип `$version` изменён с `string` на `int` для type safety.

### Производительность
- **Сериализация ответов ×3 быстрее** — замена `Symfony Serializer` (`ObjectNormalizer`) на `json_encode` через метод `toArray()` в `BaseResponse` и `ErrorResponse`.
- **Batch-запросы ×1.8 быстрее** — устранена двойная сериализация в `MultiBatchStrategy` (ранее: serialize → json_decode → json_encode; теперь: конкатенация готовых JSON-строк).
- **`HeadersPreparer`** — результат вычисляется один раз в конструкторе.
- **`checkRoles()`** — добавлен `break` после первой разрешённой роли.

---

## [3.4] - Предыдущая стабильная версия

Базовая версия бандла.
