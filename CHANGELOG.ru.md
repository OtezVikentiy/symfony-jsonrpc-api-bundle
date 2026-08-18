[English](CHANGELOG.md) · [Русский](CHANGELOG.ru.md)

# Changelog

Все значимые изменения в этом проекте документируются в этом файле.

Формат основан на [Keep a Changelog](https://keepachangelog.com/ru/1.0.0/).

---

## [Unreleased]

### Добавлено
- **Интеграция с Symfony Profiler.** В debug-приложении с включённым WebProfilerBundle toolbar показывает зарегистрированные JSON-RPC методы и входящие вызовы с замаскированными параметрами/ответами, результатом, кодами ошибок, длительностью, группировкой batch и context id из логов. Сбор не зависит от `logging.enabled`, а вне debug profiler окружения сервисы удаляются. См. [docs/profiler.ru.md](./docs/profiler.ru.md).

## [5.2] - 2026-08-17

Функциональный релиз: JSON-RPC методы теперь могут принимать загрузку файлов через `multipart/form-data`, opt-in и выключено по умолчанию. Вклад @tacman (#9), в форме, согласованной в #8. Строго обратно совместим: с выключенной функцией (по умолчанию) поведение идентично 5.1.

### Added
- **Загрузка файлов через `multipart/form-data`** — метод может принимать `Symfony\Component\HttpFoundation\File\UploadedFile` как параметр верхнего уровня. Запрос несёт поле формы `jsonrpc` с полным JSON-RPC request object (включая скалярные параметры) плюс по одной части на файл, имя части = имя параметра; всё ниже транспортного адаптера не меняется, ответ — обычный JSON-RPC-конверт. Выключено по умолчанию и включается дважды: `multipart.enabled` для приложения и `acceptsMultipart: true` в `#[JsonRPCAPI]` для метода — multipart-запрос к методу без этого флага даёт `-32600`. Batch остаётся только JSON, файлы — только на верхнем уровне `params`, только POST. Логгер вызовов пишет файл как `{originalName, size, mimeType}` и никогда как содержимое, а `acceptsMultipart: true` заставляет генератор OpenAPI опубликовать для метода тело запроса `multipart/form-data`. **Включение заново открывает — только для объявивших флаг методов — CSRF-вектор, закрытый обязательным `Content-Type: application/json` в 5.0: `multipart/form-data` является CORS «simple request». Прочитайте [docs/multipart.ru.md](./docs/multipart.ru.md) до включения.**
- **`multipart.max_file_bytes`** (по умолчанию `'10Mi'`) и **`multipart.max_files`** (по умолчанию 10) — лимиты на файл и на запрос, рядом с существующими лимитами payload/batch/depth. `max_payload_bytes` применяется к полю `jsonrpc`.
- **Параметр-файл валидируется через `Assert\File`**, скомпилированный следом за `Assert\Type` тем же механизмом, который даёт `Assert\Type('int')` для `int`-поля. `max_file_bytes` применяется именно там, в принятой в Symfony записи размера (`'10M'`, `'2Mi'` или число байт), а любая ошибка загрузки PHP — превышен `upload_max_filesize`, обрыв передачи, отсутствие временной папки — становится `-32602 Invalid params` с указанием поля, вместо непригодного `UploadedFile`, доезжающего до метода. `Assert\Type` отвечает первым, поэтому строка-путь из JSON файлом не считается.

## [5.0] - 2026-08-07

Спецификация-conformance релиз: постоянный набор тестов на соответствие [JSON-RPC 2.0 spec](https://www.jsonrpc.org/specification) нашёл семнадцать отклонений, шестнадцать из которых исправлены здесь. Подробный гайд по миграции — [docs/upgrade-5.0.md](./docs/upgrade-5.0.md).

### Добавлено
- **Permanent conformance suite** — тридцать девять тестов на разделы 4, 4.1, 4.2, 5, 5.1, 6 спецификации, включая все примеры из раздела Examples. Регресс против спека теперь падает в CI, а не проходит незамеченным.
- **CORS preflight (`OPTIONS`) обрабатывается бандлом** — маршрут `/api/v{version}` принимает `OPTIONS`, `ApiController` отвечает `204 No Content` с `Access-Control-Allow-Methods`, `Access-Control-Allow-Headers` и `Access-Control-Max-Age: 86400` до JSON-RPC-парсинга. Внешний CORS-бандл/reverse-proxy больше не обязателен.
- **`cors_allowed_headers`** (default `['Content-Type']`) — список заголовков, отражаемых в `Access-Control-Allow-Headers` preflight-ответа.
- **CI-матрица** — PHP 8.2/8.3/8.4/8.5 × Symfony 6.4/7.x/8.x, job с lowest-разрешёнными зависимостями, coverage-gate (90% минимум), `composer audit`/`composer validate --strict` еженедельно, non-blocking канарейка на Symfony 9.

### Изменено (BC-breaking)
- **Content-Type обязателен для запросов с телом.** POST/PUT/PATCH/DELETE без `Content-Type: application/json` — `-32600 Invalid Request`, даже если тело — валидный JSON. Form-encoded и multipart отклоняются на этом же основании: form-encoded — «simple request» по CORS-спеке, и без проверки сторонняя HTML-форма могла вызывать RPC-методы от имени залогиненного пользователя без preflight (CSRF). Заодно закрывает обход `max_payload_bytes`, который раньше проверял только «сырое» тело, а form-encoded PHP кладёт не туда.
- **Скаляры из query string читаются по объявленному типу.** GET-запрос не имеет тела, его payload приходит из query string, а она по природе не несёт типов — PHP разбирает её в строки. Поэтому на GET-ветке `int`, `float` и `bool` читаются так, как просит объявленный тип свойства, на любой глубине: во вложенных DTO, элементах коллекций и параметрах конструктора. Распознаются только однозначные записи (булево — набор PHP-фильтра `1/true/on/yes` и `0/false/off/no`), всё остальное отвергается валидатором как раньше: `?params[id]=abc` — `-32602`. На JSON-ветке строгость не меняется: там типы есть, и `"42"` вместо `42` остаётся ошибкой клиента.
- **Скалярные параметры больше не приводятся.** `"42"` для поля `int` — `-32602 Invalid params`, а не тихое приведение. Любой `TypeError`, всплывающий из-за клиентского ввода (конструктор DTO, сеттер, аддер, вложенный DTO), теперь превращается в `-32602` вместо `-32603`/необработанного исключения в логе.
- **Ответ сериализует только то, что класс делает публичным.** Поле попадает в JSON, если у него есть публичный геттер (`getFoo()` / `isFoo()` / `foo()`) **или** само свойство публичное. Приватное и protected без публичного геттера не попадает — это и был дефект: сериализация читала свойства напрямую через Reflection, и приватное поле (пароль, внутренний токен) уходило клиенту. Приватный геттер тоже не подходит: вызвать его снаружи нельзя. Публичные свойства, включая promoted-параметры конструктора, сериализуются как раньше — граница проходит по видимости, а не по наличию геттера. Заодно исправлено: поле, унаследованное от родительского класса, больше не теряется (`ReflectionClass::getProperties()` не возвращает приватные свойства родителя).
- **Циклический граф объектов в ответе — `-32603`, а не падение воркера.** Двунаправленная связь (заказ → пользователь → его заказы) раньше приводила к segfault: ни ответа, ни лога, `ErrorSanitizer` не успевал сработать, потому что переполнение стека — не исключение. **Заодно введён потолок вложенности — 64 уровня**, потому что самоссылающийся массив (`$a['self'] = &$a`) по идентичности не ловится: массивы в PHP значения, и `SplObjectStorage` их не различает. Ациклический, но более глубокий ответ теперь тоже отвергается — `-32603 Value nesting is too deep to serialise.` Потолок не настраивается: 64 совпадает с дефолтом `max_json_depth`, то есть более глубокую структуру всё равно не удалось бы принять на входе.
- **`DateTimeInterface` сериализуется в ISO 8601.** И в ответе (`BaseResponse`, раньше вообще не имел спецобработки — `DateTime`/`DateTimeImmutable` разваливались на пустой массив внутренних свойств), и в `JsonRpcRequest::toArray()` (раньше он не форматировал дату вовсе, а возвращал объект как есть, и тот разваливался на свойства при кодировании).
- **Методы DTO привязываются к свойствам по точному имени**, не по подстроке. `getUserId()` больше не может случайно удовлетворить и `userId`, и `id`. Аддеры коллекций резолвятся по имени свойства через `Symfony\Component\String\Inflector\EnglishInflector` (`children` → `addChild`), а не отбрасыванием последней буквы (`children` → `childre`, молча ничего не добавляя).
- **`allow_extra_fields` теперь одинаково применяется на любой глубине.** Раньше флаг проверялся один раз на верхнем уровне, а рекурсивная гидратация вложенных DTO отклоняла лишние поля безусловно.
- **Batch определяется по форме контейнера**, а не по первому элементу. Любой непустой JSON-массив-список — batch, даже если часть элементов невалидна. Раньше невалидный первый элемент откатывал всю обработку к single-request режиму, и валидные вызовы дальше в массиве терялись без следа.
- **`{"id": null}` — полноценный запрос**, получает ответ с `"id": null`. Notification — это отсутствие поля `id`, а не `null`-значение; раньше `isset()` не различал эти случаи. Notification теперь не получает ответа вообще, включая ошибочный результат (кроме случая, когда конверт запроса настолько повреждён, что нельзя достоверно установить, был ли это notification).
- **`params: null` отклоняется** (`-32600`), а не тихо трактуется как «параметров нет» — та же `isset()`-ловушка, что и для `id`.
- **Отказ по ролям — `-32000` в JSON-RPC error-объекте, HTTP 200**, не HTTP 403 с голой строкой. Раньше внутри batch эта голая строка портила JSON-структуру элемента и теряла `id`.
- **Коды ошибок вне допустимых JSON-RPC диапазонов нормализуются в `-32603`.** Произвольный `Throwable` с кодом `0` (или любым другим вне стандартных значений и `-32000..-32099`) больше не долетает до клиента как есть, даже при `expose_internal_errors: true`.
- **Имена схем OpenAPI не сталкиваются.** Схема ответа — `{methodName}Response`, вложенные DTO — по полному имени класса (`\` → `.`). Раньше короткое имя класса (`Response`, `Request`) собирало все одноимённые DTO проекта в одну схему, тихо затирая предыдущую.
- **`cors_strict` удалён.** Поведение всегда как было при `cors_strict: true` в 4.x — легаси comma-joined режима не существует. Оставшийся в конфиге ключ `cors_strict` теперь ошибка компиляции контейнера, а не молчаливо игнорируемое значение.
- **Границы зависимостей в манифесте.** `composer.json` теперь объявляет верхние границы (`^6.4 || ^7.0 || ^8.0` для Symfony-компонентов, `8.2.* || 8.3.* || 8.4.* || 8.5.*` для PHP) вместо открытых `>=`. Границы отражают то, что проверяет CI: десять сочетаний PHP × Symfony, кроме Symfony 8 на PHP ниже 8.4 — Symfony 8 требует PHP >= 8.4.1. `doctrine/annotations` убран из require — ничего в `src/`/`config/` на него не полагалось.
- **Контейнер больше не алиасится в приложение, RPC-методы больше не публичные сервисы.** `config/services.yaml` алиасил `Symfony\Component\DependencyInjection\Container` на `@service_container`, из-за чего любой сервис приложения-потребителя мог заавтовайрить через этот бандл весь контейнер целиком. Алиас был и причиной, по которой каждый RPC-метод регистрировался публичным сервисом. `RequestHandler` теперь получает `ServiceLocator`, собранный по тегу `ov.rpc.method`, и ни алиас, ни публичность не нужны. **Замены алиасу нет намеренно:** если ваш сервис типизировал конструктор как `Container`, инжектируйте нужные сервисы напрямую. Девятнадцать классов в `src/DependencyInjection/` и `src/Swagger/` помечены `@internal`, `MethodSpecCollection::getMethodNames()` удалён (возвращал номера версий API, а не имена методов).
- **Маскирование чувствительных данных в логах включено по умолчанию.** Раньше `logging.masking.key_patterns` был пустым списком, а `logging.max_body_length` — нулём, поэтому одной строки `logging.enabled: true` хватало, чтобы в лог пошли полные тела запросов и ответов вместе с паролями, а оператору полагалось самому догадаться про список паттернов. Теперь `key_patterns` содержит двадцать девять паттернов, а `max_body_length` — 8192. Приложения, у которых логирование уже включено, увидят `***` на месте значений и обрезанные тела. Битое регулярное выражение в `key_patterns` теперь роняет компиляцию контейнера вместо тихого отключения маскировки для этого паттерна в рантайме.
- **Исключение из PostProcessor больше не всплывает.** Блок `finally` в `processBatch()` обёрнут в `try/catch (Throwable)`: сбой пишется в логгер сообщением `JSON-RPC post-response stage failed` и на ответ не влияет. `finally` выполняется уже после формирования ответа, а в batch-режиме — после каждого элемента, поэтому исключение оттуда затирало готовый результат ошибкой и могло оборвать обработку остальных элементов. **PostProcessor, бросавший намеренно** (провалить аудит, отказать в выдаче ответа), теперь ни на что не влияет — перенесите такую логику в сам метод или в PreProcessor. См. [docs/upgrade-5.0.md](./docs/upgrade-5.0.md) п. 22.
- **`JsonRpcRequest::toArray()` сериализует так же, как ответ.** Метод публичный и документация предлагает его для логирования запроса, но он читал свойства напрямую через Reflection — то есть приватное поле без геттера (пароль, внутренний токен) уходило в лог. Теперь поле экспортируется, только если у него есть публичный геттер. Взаимные ссылки между Request-DTO раньше приводили к переполнению стека и падению воркера без ответа и без записи в лог; теперь бросается `JRPCException` с кодом `-32603` — **метод стал способен выбрасывать исключение.** Вложенный объект со своим `toArray()` по-прежнему решает свою форму сам; исключение — другой `JsonRpcRequest`, он обходится общим механизмом, иначе обход начинался бы заново и цикл снова ронял бы процесс.
- **`ResponseService` требует `ErrorSanitizer` явно.** Второй параметр конструктора перестал быть необязательным: `new ResponseService($headersPreparer)` теперь `ArgumentCountError`. Раньше тестовый харнесс мог собрать `ResponseService` без санитайзера, и тесты валидировали конфигурацию, никогда не существовавшую в проде.
- **`HeadersPreparer` сменил третий параметр конструктора.** Вместо удалённого `bool $corsStrict` там теперь `array $allowedHeaders` — старый позиционный вызов `new HeadersPreparer($list, $stack, true)` даёт `TypeError`, а не игнорируемое значение. При пустом whitelist origin'ов `prepareHeaders()` возвращает пустой массив вместо `['Access-Control-Allow-Origin' => '']`: пустой заголовок больше не отдаётся вовсе.

- **Конвейер обработки запроса помечен `@internal`.** К девятнадцати классам из `src/DependencyInjection/` и `src/Swagger/` добавились `ApiController`, `RequestHandler`, `RequestRawDataHandler`, `ResponseService`, `HeadersPreparer`, `ErrorSanitizer`, `BatchStrategyFactory`, обе batch-стратегии, `HandleBatchInterface` и трейт сериализации — всего тридцать. Ни один из них приложение не конструирует само, это делает контейнер. Публичным контрактом остаются интерфейсы точек расширения, `JsonRpcRequest`, `BaseRequest`, `BaseResponse`, `JRPCException`, атрибут `#[JsonRPCAPI]` и ключи конфигурации.
- **`'*'` рядом с конкретными origin'ами больше не компилируется.** Wildcard проверяется первым и выигрывает, поэтому `['https://app.example.com', '*']` отвечал `Access-Control-Allow-Origin: *` вообще всем: список читался как whitelist и работал наоборот, и по поведению приложения разницу было не увидеть. Либо перечислите origin'ы, либо используйте `'*'` в одиночку.

### Исправлено
- **`Vary: Origin` отдаётся и при отказе, а не только при совпадении.** Наличие CORS-заголовка зависит от `Origin` в обе стороны, и разделяемый кэш, не знающий об этом, мог отдать безголовую версию ответа клиенту с разрешённым origin'ом — или заголовок одного origin'а другому.
- **`Content-Type: application/json` с NUL-байтом больше не принимается.** У `trim()` в списке символов по умолчанию есть `\0`, поэтому media type, дополненный управляющим байтом, проходил сравнение. Обрезаются только пробелы и табы.
- **Невалидный `id` в ответе теперь `null`, а не отражается обратно.** Спецификация, раздел 5: «If there was an error in detecting the id in the Request Object ... it MUST be Null». `BaseRequest` отвергает `id` типа boolean, массив и объект, но на пути ошибки сырое значение бралось из декодированного payload'а без этой проверки — и ответ, сообщающий о невалидном запросе, сам оказывался невалидным. Побочно это был канал отражения произвольной структуры размером до `max_payload_bytes`. Три conformance-теста на эти случаи существовали, но проверяли только `error.code` и не смотрели на `id`.
- **Пропущенное поле-объект — `-32602`, а не `-32603`.** Поле, которого не было в запросе, не гидрировалось, а стадия валидации всё равно читала его через геттер: типизированное свойство без значения даёт `Error` («must not be accessed before initialization»), а не `JRPCException`, и санитайзер превращал это во «внутреннюю ошибку сервера». Клиент, забывший поле, получал сообщение о поломке сервера. Дефект зависел от порядка загрузки классов — `class_exists()` здесь вызывается без автозагрузки, поэтому проявлялся не всегда.
- **Пустая коллекция `{"items": []}` принимается.** Ветка adder'а была закрыта проверкой `!empty()`, поэтому пустой список уходил в ветку сеттера — где тип уже переписан на тип *элемента* коллекции, и пустой массив собирался в один голый элемент. Клиент получал `[items] - This value should be of type Item` и не мог отправить пустой список никак. Прочие значения ветку не сменили: `null` и `0` по-прежнему идут в сеттер, строка по-прежнему отвергается с «must be an array».
- **Геттер DTO резолвится одинаково на обеих стадиях сборки.** Сбор валидаторов выводил единственное жёсткое имя — `isX` для boolean-свойства, `getX` для остальных — и выполнялся раньше, поэтому решал за всех. Boolean-свойство `$isActive` с естественным аксессором `isActive()` роняло компиляцию контейнера, требуя метод `isIsActive()`, а голый аксессор (`title()`), заявленный в другом резолвере, был недостижим. Обе стадии используют один список кандидатов: `getX`, `isX`, `x`; сообщение об ошибке перечисляет все три формы вместо одной.
- **Позиционные параметры (`params` массивом, раздел 4.2 спецификации) снова работают.** Гидрация всегда понимала псевдо-поле `params`, а стадия валидации — нет: она видела список с ключами `0..n`, сообщала, что поле `params` отсутствует, а каждый элемент лишний, и отклоняла любой by-position вызов с `-32602`. Ломались все примеры из спецификации и из документации, включая канонический `subtract` с `[42,23]`. Дефект не был замечен потому, что conformance-сюита работала с мок-валидатором, который возвращает пустой список нарушений на любой вход, — сюита теперь прогоняет настоящий валидатор.
- **Сообщение об ошибке конструктора Request-DTO больше не содержит путей на сервере и внутренних имён классов.** `TypeError` от конструктора несёт абсолютный путь файла, номер строки и FQCN, и это попадало клиенту при дефолтном `expose_internal_errors: false`: ошибка заворачивалась в `JRPCException`, а `ErrorSanitizer` такие пропускает по определению. Из исходного сообщения теперь берётся только позиция аргумента, а текст для клиента собирается из спецификации метода в том же формате, что и на пути гидрации: `[id] - This value should be of type int`.
- Well-formed JSON, не являющийся Request-объектом (`"42"`, голая строка, `true`, `null`) — теперь `-32600 Invalid Request` по разделу 5.1 спека, а не `-32700 Parse error`. Невалидный JSON по-прежнему `-32700`.
- GET-ветка теперь применяет `max_payload_bytes` и `max_json_depth` — раньше оба лимита обходились переносом payload'а в query string. **Асимметрия:** на GET-ветке `max_json_depth` фактически ограничен `max_input_nesting_level` из `php.ini` (default 64) — PHP разбирает query string раньше, чем бандл получает управление, и обрезает более глубокие структуры сам. На POST-ветке лимит honours полностью.

### Известные ограничения
- `id`, превышающий `PHP_INT_MAX`, не возвращается байт-в-байт: `json_decode()` превращает его во `float` с потерей точности.

---

## [4.2] - 2026-05-19

### Добавлено
- **Pluggable PSR-3 logger** — два новых конфига `logging.logger_service` и `logging.call_logger_service` плюс bundle-scoped alias `ov_json_rpc_api.logger`. Позволяют подменять либо внутренний PSR-3 sink, который `JsonRpcCallLogger` использует для записи (`logger_service` / alias `ov_json_rpc_api.logger`), либо целиком реализацию `JsonRpcCallLoggerInterface` (`call_logger_service` / alias `JsonRpcCallLoggerInterface`). Alias, заданный вручную в проектном `services.yaml`, выигрывает у config-ключа и даже у `logging.enabled: false` kill-switch — Symfony DI мерджит проектный `services.yaml` в контейнер раньше, чем компилируется расширение бандла. Override-точка для `JsonRpcLogFormatterInterface` из 4.1 не изменена. Подробности и полная таблица precedence — [docs/logging.md](./docs/logging.ru.md).

---

## [4.1] - 2026-05-14

### Добавлено
- **Request/Response logging** — опциональная подсистема, выключена по умолчанию. Конфиг `ov_json_rpc_api.logging.enabled: true` включает дефолтный логгер с форматом `Request: [method] {body} context_id: <uuid>` и парным `Response`. Поддерживает маскировку sensitive-полей по регексам на имена ключей JSON (`logging.masking.key_patterns`), кастомный форматтер (`JsonRpcLogFormatterInterface`), кастомный маскер (`SensitiveDataMaskerInterface`), кастомный генератор context_id (`ContextIdGeneratorInterface`). Подробности и примеры — [docs/logging.md](./docs/logging.ru.md).

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
  - [docs/security_hardening.md](./docs/security_hardening.ru.md) — все новые конфиги + tuning.
  - [docs/upgrade-4.0.md](./docs/upgrade-4.0.md) — миграция с 3.x.
  - [docs/cors.md](./docs/cors.ru.md) — CORS-поведение.
  - [docs/batch.md](./docs/batch.ru.md) — batch-семантика и лимиты.
  - [docs/testing.md](./docs/testing.ru.md) — гайд по написанию тестов для RPC-методов.
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
