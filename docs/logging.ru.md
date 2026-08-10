[English](logging.md) · [Русский](logging.ru.md)

# JSON-RPC Request/Response Logging

Бандл умеет писать пару записей `Request` / `Response` на каждый JSON-RPC вызов через стандартный Symfony PSR-3 logger. По умолчанию выключено — никакого оверхеда без явного opt-in.

## Включение

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    logging:
        enabled: true
```

Этого достаточно: с 5.0 маскирование работает из коробки — `key_patterns` содержит 29 паттернов, а тела обрезаются до 8192 символов.

> ⚠️ **`key_patterns` — замена списка, а не дополнение.** Задав свой список, вы выключаете все 29 дефолтных. И задавайте паттерны **без якорей**: `~^password$~i` не поймает `user_password`, `pwd_hash` или `X-Auth-Token` — то есть ровно те имена, за которыми секреты обычно и прячутся. Дефолтный список неякорный намеренно, разбор причины — в docblock `Configuration::DEFAULT_MASKING_KEY_PATTERNS`. Если нужно добавить своё, скопируйте дефолты и дополните:

```yaml
ov_json_rpc_api:
    logging:
        enabled: true
        masking:
            key_patterns:
                # ... сюда скопируйте нужные из Configuration::DEFAULT_MASKING_KEY_PATTERNS ...
                - '~password~i'
                - '~token~i'
                # ... и добавьте свои, тоже без якорей
                - '~internal_ref~i'
```

## Дефолтный формат

```
Request: [get_billing_operations] {"jsonrpc":"2.0","method":"get_billing_operations","params":{"user_id":42},"id":1} context_id: 9f3a1d2e-...
Response: [get_billing_operations] {"jsonrpc":"2.0","result":{"count":7},"id":1} context_id: 9f3a1d2e-...
```

Парность Request/Response гарантирована: одинаковый `context_id` всегда указывает на одну пару. В батче из N вызовов — N разных `context_id`, по одному на пару Request/Response.

## Полная схема конфига

| Параметр | Default | Описание |
|---|---|---|
| `logging.enabled` | `false` | Главный switch. При `false` логгер — no-op без накладных расходов (Null Object pattern). |
| `logging.request_level` | `info` | PSR-3 уровень для Request. |
| `logging.response_level` | `info` | PSR-3 уровень для успешных Response. |
| `logging.error_response_level` | `warning` | PSR-3 уровень для Response с error-объектом. |
| `logging.max_body_length` | `8192` | Обрезка body после маскировки (0 = без обрезки, не рекомендуется). Маркер `...[truncated, N total bytes]`. |
| `logging.skip_plain_responses` | `true` | Для `PlainResponseInterface` body заменяется на `[plain response, N bytes]`. |
| `logging.masking.placeholder` | `***` | Что подставлять вместо матченных значений. |
| `logging.masking.key_patterns` | набор regex-ов для типовых имён секретов (`password`, `token`, `jwt`, `secret`, `api_key`, `authorization`, `session_id`, `card_number`, `cvv`, `ssn`, `cert`, …) | Список PCRE-регексов. Совпадение по имени ключа JSON — значение целиком заменяется на placeholder. Применяется рекурсивно на любой глубине. Битый регекс валится на этапе сборки контейнера (`InvalidConfigurationException`), а не молча отключает маскировку в рантайме. Полный список — `Configuration::DEFAULT_MASKING_KEY_PATTERNS`. |
| `logging.logger_service` | `null` | ID Symfony-сервиса PSR-3 `LoggerInterface`, который бандл использует как sink (см. «Кастомный PSR-3 логгер»). `null` = стандартный `@logger`. |
| `logging.call_logger_service` | `null` | ID Symfony-сервиса, реализующего `JsonRpcCallLoggerInterface`. Подменяет всю high-level обвязку бандла (pairing/encoding/masking). Учитывается только при `enabled: true` (см. «Полная замена JsonRpcCallLoggerInterface»). |

> **Изменение дефолтов (breaking change).** Начиная с этой версии `logging.masking.key_patterns` и `logging.max_body_length` больше не пустые/нулевые по умолчанию. Если вы уже используете `logging.enabled: true` без явной настройки маскировки, в логах начнут появляться `***` там, где раньше были полные значения, а длинные тела будут обрезаться маркером `...[truncated, N total bytes]`. Чтобы вернуть старое поведение — задайте `key_patterns: []` и/или `max_body_length: 0` явно.

`method` в записи лога всегда обрезается до 128 символов и экранируется (`\r`, `\n`, `\t`, прочие control-байты) до попадания в message — это защита от log injection через ещё не провалидированное значение `method` и не зависит от `max_body_length`.

> **Ограничение маскирования: только по имени ключа.** `SensitiveDataMasker` смотрит исключительно на имя ключа JSON, а не на форму значения. Секрет, попавший в поле с неподходящим именем, замаскирован не будет — например, `"note": "my password is hunter2"` или произвольная JSON-строка, вложенная как значение (сериализованная вручную), не совпадут ни с одним `key_patterns`, даже если внутри буквально лежит пароль. То же с JWT: паттерн `~jwt~i` матчит поле по имени (`jwt`, `jwt_token`), но не опознаёт токен по характерной форме `xxx.yyy.zzz` — если тот же токен передан в поле `access_token` (уже покрыт `~token~i`) всё в порядке, а вот в поле с произвольным именем (`value`, `data`, `payload`) — нет. Это архитектурное свойство маскера, а не забытый паттерн: значение-ориентированное обнаружение (например, детекция JWT по форме) в этой версии не реализовано.

> **Следствие: позиционные параметры не маскируются никогда.** Спецификация разрешает передавать `params` массивом (раздел 4.2), и элементы такого массива не имеют имён — только порядковые номера. Маскировать нечего по имени, поэтому `{"method":"login","params":["alice","hunter2"]}` уходит в лог как есть при любом списке паттернов. Если метод принимает секреты и логирование включено — передавайте параметры по имени (`{"login":"alice","password":"hunter2"}`), тогда `password` попадёт под дефолтный паттерн.

> **Паттерн с обратной ссылкой на номер группы не участвует в слиянии.** Для скорости паттерны с одинаковыми флагами склеиваются в одну альтернацию, а это перенумеровывает группы захвата — паттерн вида `~(x)y\1~` после склейки указывал бы на чужую группу и продолжал бы компилироваться и матчиться, просто не то. Такие паттерны (`\1`, `\g{1}`, `(?(1)…)`) проверяются по отдельности. На корректность это не влияет, на скорость — незначительно; именованные группы можно использовать свободно.

## Переопределение формата

Реализуйте `OV\JsonRPCAPIBundle\Core\Logging\JsonRpcLogFormatterInterface` и переопределите alias в своём `config/services.yaml`:

```yaml
services:
    App\Logging\MyJsonRpcLogFormatter: ~

    OV\JsonRPCAPIBundle\Core\Logging\JsonRpcLogFormatterInterface:
        alias: App\Logging\MyJsonRpcLogFormatter
```

Пример класса:

```php
<?php

namespace App\Logging;

use OV\JsonRPCAPIBundle\Core\Logging\Direction;
use OV\JsonRPCAPIBundle\Core\Logging\FormattedLogEntry;
use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcLogEntry;
use OV\JsonRPCAPIBundle\Core\Logging\JsonRpcLogFormatterInterface;

final class MyJsonRpcLogFormatter implements JsonRpcLogFormatterInterface
{
    public function format(JsonRpcLogEntry $entry): FormattedLogEntry
    {
        return new FormattedLogEntry(
            message: sprintf('[%s] %s: %s', $entry->contextId, $entry->direction->value, $entry->body),
            context: ['method' => $entry->method ?? 'unknown'],
            level: 'info',
        );
    }
}
```

## Кастомный маскер

```yaml
services:
    OV\JsonRPCAPIBundle\Core\Logging\SensitiveDataMaskerInterface:
        alias: App\Logging\MyCustomMasker
```

## Кастомный генератор context_id

По умолчанию — UUID v4. Можно подменить, например на пробрасывание `X-Request-Id` из HTTP-заголовка:

```yaml
services:
    OV\JsonRPCAPIBundle\Core\Logging\ContextIdGeneratorInterface:
        alias: App\Logging\HeaderBasedContextIdGenerator
```

## Поведение в граничных случаях

- **Notification (id отсутствует)** — Request пишется. Response — `[no response - notification]`. Парность сохранена.
- **PlainResponse (бинарный)** — body заменяется на `[plain response, N bytes]`. Маскировка не запускается.
- **Parse error (невалидный JSON в body)** — Request пишется через `logRawRequest`. Если тело удалось распарсить (например, превышен `max_json_depth` но JSON валиден) — маскировка применяется; иначе body — `[unparseable body, N bytes]`. Сырые байты в лог не уходят — это намеренная защита от утечки секретов в случае мусора/бинаря.
- **Batch size exceeded** — одна пара Request/Response, method = `unknown`.
- **Ошибка внутри логгера** — никогда не пробивается в бизнес-пайплайн. Запись `error` в основном логе с trace; обработка запроса продолжается без логирования этого вызова.

## Кастомный PSR-3 логгер

Внутри `JsonRpcCallLogger` бандл использует PSR-3 `LoggerInterface` как sink — туда уходят уже отформатированные записи. По умолчанию это стандартный Symfony-сервис `logger` (то есть Monolog). Можно подменить на любой свой PSR-3 логгер — например, уже сконфигурированный со своим Monolog handler/processor/formatter — двумя способами.

**Через `logger_service` (рекомендованный):**

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    logging:
        enabled: true
        logger_service: App\Infrastructure\Logger\MyCustomLogger
```

**Через alias в `services.yaml`:**

```yaml
services:
    ov_json_rpc_api.logger:
        alias: App\Infrastructure\Logger\MyCustomLogger
```

Если заданы оба — побеждает alias в `services.yaml` (проектный `services.yaml` разрешается раньше, чем компилируется расширение бандла, и подставленный там alias накладывается поверх результата компиляции — подробности и полная таблица приоритетов ниже, в разделе «Приоритет override-механизмов»). Сервис должен реализовывать `Psr\Log\LoggerInterface`. Бандл по-прежнему отвечает за pairing, маскировку, обрезку body и формирование message/context/level через `JsonRpcLogFormatterInterface`; кастомный логгер получает финальный `log($level, $message, $context)` со всеми его handler/processor.

### Разделение по monolog-каналам

Симметрично — указываем сервис monolog-канала:

```yaml
ov_json_rpc_api:
    logging:
        logger_service: monolog.logger.json_rpc_api
```

## Полная замена JsonRpcCallLoggerInterface

Если нужно обойти всю высокоуровневую логику бандла (pairing, encoding, masking, formatter) — реализуйте `JsonRpcCallLoggerInterface` целиком и подмените дефолт.

**Через `call_logger_service`:**

```yaml
ov_json_rpc_api:
    logging:
        enabled: true
        call_logger_service: App\Logging\MyJsonRpcCallLogger
```

**Через alias в `services.yaml`:**

```yaml
services:
    OV\JsonRPCAPIBundle\Core\Logging\JsonRpcCallLoggerInterface:
        alias: App\Logging\MyJsonRpcCallLogger
```

`logging.enabled: false` — kill-switch **только пока вы не задали alias вручную в `services.yaml`**: если alias на `JsonRpcCallLoggerInterface` прописан в проектном `services.yaml`, он победит даже при `enabled: false`. Если задано и через config (`call_logger_service`), и через alias в `services.yaml` — выигрывает alias. Подробности — в разделе «Приоритет override-механизмов» ниже.

Контракт интерфейса (`logRequest` / `logRawRequest` / `logResponse`) — см. `src/Core/Logging/JsonRpcCallLoggerInterface.php`. При своей реализации `JsonRpcLogFormatterInterface`, `SensitiveDataMaskerInterface`, `ContextIdGeneratorInterface` бандлом не используются — вы их можете подключать или нет на своё усмотрение.

## Приоритет override-механизмов

> **Важно, если вы полагаетесь на `logging.enabled: false` как на способ гарантированно выключить логирование:** alias, заданный в вашем собственном `config/services.yaml`, побеждает **всё**, включая kill-switch. Ниже — фактический порядок, проверенный интеграционным тестом на реальном `ContainerBuilder` (а не только предположением о том, как должен вести себя Symfony DI).

Причина в механике Symfony DI: `config/services.yaml` проекта обрабатывается и попадает в контейнер раньше, чем компилируется расширение бандла. `MergeExtensionConfigurationPass` запоминает уже существующие definitions/aliases контейнера **до** вызова `OVJsonRPCAPIExtension::load()`, а после мерджа результатов extension обратно накладывает эти исходные aliases поверх — так что то, что явно прописано в вашем `services.yaml`, всегда оказывается «последним словом», независимо от того, что решил сделать бандл.

PSR-3 logger (`ov_json_rpc_api.logger`):

1. **`ov_json_rpc_api.logger` alias в вашем `services.yaml` — выигрывает всегда**, даже если конфиг ничего не задаёт.
2. `logging.logger_service` (config) — применяется, если alias в `services.yaml` не переопределён.
3. Default — `@logger` (регистрируется bundle-собственным `config/services.yaml`).

`JsonRpcCallLoggerInterface`:

1. **`JsonRpcCallLoggerInterface` alias в вашем `services.yaml` — выигрывает всегда**, даже над `logging.enabled: false`. Если вы вручную прописали alias на свой логгер, выключить его через `enabled: false` не получится — удалите alias из `services.yaml`, если нужен настоящий kill-switch.
2. `logging.enabled: false` → `NullJsonRpcCallLogger`, kill-switch, если alias в `services.yaml` не задан.
3. `logging.call_logger_service` (config) — применяется, если `enabled: true` и alias в `services.yaml` не задан.
4. Default — `JsonRpcCallLogger` (при `enabled: true`).

Другими словами: `services.yaml` — это ручной, явный override, который стоит **выше** и config-ключей, и kill-switch'а. Config-ключи (`logger_service` / `call_logger_service`) — способ переопределить sink без ручного вмешательства в `services.yaml`; `logging.enabled` — дефолтный переключатель на случай, когда ни то, ни другое не задано.

## Производительность

При `enabled: false` весь подсистема — single Null Object с тремя no-op методами. Накладные на отключённом логировании пренебрежимы (1 вызов метода на каждый Request/Response, делегируемый JIT-инлайнящему пустому телу).

При `enabled: true` основная стоимость — `json_decode` + рекурсивный обход для маскировки + `json_encode`. Для типичных JSON-RPC тел это <1 ms. Если объём логов критичен — настройте `max_body_length` для обрезки больших полезных нагрузок.
