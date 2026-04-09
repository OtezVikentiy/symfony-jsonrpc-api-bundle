# Changelog

Все значимые изменения в этом проекте документируются в этом файле.

Формат основан на [Keep a Changelog](https://keepachangelog.com/ru/1.0.0/).

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
