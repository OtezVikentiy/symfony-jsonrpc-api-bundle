# JSON-RPC Request/Response Logging

Бандл умеет писать пару записей `Request` / `Response` на каждый JSON-RPC вызов через стандартный Symfony PSR-3 logger. По умолчанию выключено — никакого оверхеда без явного opt-in.

## Включение

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    logging:
        enabled: true
        masking:
            key_patterns:
                - '~^(password|secret|token|authorization)$~i'
                - '~^card_number$~i'
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
| `logging.max_body_length` | `0` | Обрезка body после маскировки (0 = без обрезки). Маркер `...[truncated, N total bytes]`. |
| `logging.skip_plain_responses` | `true` | Для `PlainResponseInterface` body заменяется на `[plain response, N bytes]`. |
| `logging.masking.placeholder` | `***` | Что подставлять вместо матченных значений. |
| `logging.masking.key_patterns` | `[]` | Список PCRE-регексов. Совпадение по имени ключа JSON — значение целиком заменяется на placeholder. Применяется рекурсивно на любой глубине. |

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

## Разделение по monolog-каналам

Бандл использует автовайренный `Psr\Log\LoggerInterface`. Если нужно писать логи в отдельный monolog-канал, переопределите алиас `LoggerInterface` в своём `config/services.yaml`:

```yaml
services:
    OV\JsonRPCAPIBundle\Core\Logging\JsonRpcCallLogger:
        arguments:
            $logger: '@monolog.logger.json_rpc_api'
```

или через декоратор. Бандл сам каналы не настраивает.

## Производительность

При `enabled: false` весь подсистема — single Null Object с тремя no-op методами. Накладные на отключённом логировании пренебрежимы (1 вызов метода на каждый Request/Response, делегируемый JIT-инлайнящему пустому телу).

При `enabled: true` основная стоимость — `json_decode` + рекурсивный обход для маскировки + `json_encode`. Для типичных JSON-RPC тел это <1 ms. Если объём логов критичен — настройте `max_body_length` для обрезки больших полезных нагрузок.
