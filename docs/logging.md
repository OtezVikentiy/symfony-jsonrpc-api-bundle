[English](logging.md) · [Русский](logging.ru.md)

# JSON-RPC request/response logging

The bundle can write a pair of `Request` / `Response` entries for every JSON-RPC call through the standard Symfony PSR-3 logger. It is off by default — no overhead without an explicit opt-in.

## Turning it on

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    logging:
        enabled: true
```

That is enough: since 5.0 masking works out of the box — `key_patterns` holds 29 patterns, and bodies are truncated at 8192 characters.

> ⚠️ **`key_patterns` replaces the list; it does not extend it.** Supplying your own switches off all 29 defaults. And write the patterns **without anchors**: `~^password$~i` will not catch `user_password`, `pwd_hash` or `X-Auth-Token` — precisely the names secrets usually hide behind. The default list is unanchored deliberately; the reasoning is in the docblock of `Configuration::DEFAULT_MASKING_KEY_PATTERNS`. To add your own, copy the defaults and extend them:

```yaml
ov_json_rpc_api:
    logging:
        enabled: true
        masking:
            key_patterns:
                # ... copy the ones you need from Configuration::DEFAULT_MASKING_KEY_PATTERNS ...
                - '~password~i'
                - '~token~i'
                # ... and add your own, also without anchors
                - '~internal_ref~i'
```

## The default format

```
Request: [get_billing_operations] {"jsonrpc":"2.0","method":"get_billing_operations","params":{"user_id":42},"id":1} context_id: 9f3a1d2e-...
Response: [get_billing_operations] {"jsonrpc":"2.0","result":{"count":7},"id":1} context_id: 9f3a1d2e-...
```

Request/response pairing is guaranteed: an identical `context_id` always points at one pair. In a batch of N calls there are N different `context_id`s, one per pair.

## The full configuration

| Key | Default | Description |
|---|---|---|
| `logging.enabled` | `false` | The master switch. At `false` the logger is a no-op with no overhead (the Null Object pattern). |
| `logging.request_level` | `info` | The PSR-3 level for a Request. |
| `logging.response_level` | `info` | The PSR-3 level for a successful Response. |
| `logging.error_response_level` | `warning` | The PSR-3 level for a Response carrying an error object. |
| `logging.max_body_length` | `8192` | Truncation of the body after masking (0 = no truncation, not recommended). The marker is `...[truncated, N total bytes]`. |
| `logging.skip_plain_responses` | `true` | For a `PlainResponseInterface` the body is replaced by `[plain response, N bytes]`. |
| `logging.masking.placeholder` | `***` | What to substitute for matched values. |
| `logging.masking.key_patterns` | a set of regexes for the usual secret names (`password`, `token`, `jwt`, `secret`, `api_key`, `authorization`, `session_id`, `card_number`, `cvv`, `ssn`, `cert`, …) | A list of PCRE regexes. A match against a JSON key name replaces the whole value with the placeholder. Applied recursively at any depth. A broken regex fails container compilation (`InvalidConfigurationException`) rather than quietly disabling masking at runtime. The full list is in `Configuration::DEFAULT_MASKING_KEY_PATTERNS`. |
| `logging.logger_service` | `null` | The id of a Symfony service implementing PSR-3 `LoggerInterface`, used by the bundle as its sink (see "A custom PSR-3 logger"). `null` means the standard `@logger`. |
| `logging.call_logger_service` | `null` | The id of a Symfony service implementing `JsonRpcCallLoggerInterface`. Replaces the bundle's whole high-level layer (pairing, encoding, masking). Honoured only while `enabled: true` (see "Replacing JsonRpcCallLoggerInterface entirely"). |

> **A change of defaults (breaking).** From this version `logging.masking.key_patterns` and `logging.max_body_length` are no longer empty and zero by default. If you already run `logging.enabled: true` without configuring masking, `***` will start appearing in the logs where full values used to be, and long bodies will be truncated with a `...[truncated, N total bytes]` marker. To restore the old behaviour, set `key_patterns: []` and/or `max_body_length: 0` explicitly.

The `method` in a log entry is always truncated to 128 characters and escaped (`\r`, `\n`, `\t` and other control bytes) before it reaches the message. That guards against log injection through a `method` value that has not been validated yet, and is independent of `max_body_length`.

> **A limit of masking: key names only.** `SensitiveDataMasker` looks at the JSON key name and nothing else — never at the shape of the value. A secret landing in a field with an unmatched name is not masked: `"note": "my password is hunter2"`, or an arbitrary JSON string nested as a value (serialised by hand), match no `key_patterns` even with a password literally inside. The same holds for JWTs: the `~jwt~i` pattern matches a field by name (`jwt`, `jwt_token`) but does not recognise a token by its characteristic `xxx.yyy.zzz` shape — the same token in an `access_token` field is fine (covered by `~token~i`), while in a field with an arbitrary name (`value`, `data`, `payload`) it is not. This is an architectural property of the masker, not a forgotten pattern: value-oriented detection, such as spotting a JWT by its shape, is not implemented in this version.

> **A consequence: positional parameters are never masked.** The specification permits `params` to be an array (section 4.2), and the elements of such an array have no names — only ordinal positions. There is nothing to match by name, so `{"method":"login","params":["alice","hunter2"]}` reaches the log as-is under any pattern list. If a method takes secrets and logging is on, pass the parameters by name (`{"login":"alice","password":"hunter2"}`), and `password` falls under the default pattern.

> **A pattern with a backreference to a group number is not merged.** For speed, patterns sharing flags are joined into one alternation, which renumbers the capture groups — a pattern like `~(x)y\1~` would, after joining, point at somebody else's group and go on compiling and matching, just not the right thing. Such patterns (`\1`, `\g{1}`, `(?(1)…)`) are checked separately. Correctness is unaffected; speed is affected negligibly. Named groups can be used freely.

## Overriding the format

Implement `OV\JsonRPCAPIBundle\Core\Logging\JsonRpcLogFormatterInterface` and override the alias in your own `config/services.yaml`:

```yaml
services:
    App\Logging\MyJsonRpcLogFormatter: ~

    OV\JsonRPCAPIBundle\Core\Logging\JsonRpcLogFormatterInterface:
        alias: App\Logging\MyJsonRpcLogFormatter
```

An example class:

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

## A custom masker

```yaml
services:
    OV\JsonRPCAPIBundle\Core\Logging\SensitiveDataMaskerInterface:
        alias: App\Logging\MyCustomMasker
```

## A custom context_id generator

The default is a UUID v4. It can be replaced — with something that carries an `X-Request-Id` HTTP header through, for instance:

```yaml
services:
    OV\JsonRPCAPIBundle\Core\Logging\ContextIdGeneratorInterface:
        alias: App\Logging\HeaderBasedContextIdGenerator
```

## Behaviour in edge cases

- **A Notification (no id)** — the Request is written. The Response is `[no response - notification]`. Pairing is preserved.
- **A PlainResponse (binary)** — the body is replaced by `[plain response, N bytes]`. Masking does not run.
- **A parse error (invalid JSON in the body)** — the Request is written through `logRawRequest`. If the body could be parsed after all (`max_json_depth` exceeded but the JSON valid, say) masking is applied; otherwise the body is `[unparseable body, N bytes]`. Raw bytes never reach the log — a deliberate guard against leaking secrets out of rubbish or binary data.
- **Batch size exceeded** — one Request/Response pair, with method = `unknown`.
- **An error inside the logger** — never reaches the business pipeline. An `error` entry with a trace is written to the main log, and request handling carries on without logging that call.

## A custom PSR-3 logger

Inside `JsonRpcCallLogger` the bundle uses a PSR-3 `LoggerInterface` as its sink — already-formatted entries go there. By default that is Symfony's standard `logger` service (Monolog). It can be replaced by any PSR-3 logger of your own — one already configured with your Monolog handler, processor and formatter, for example — in two ways.

**Through `logger_service` (recommended):**

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    logging:
        enabled: true
        logger_service: App\Infrastructure\Logger\MyCustomLogger
```

**Through an alias in `services.yaml`:**

```yaml
services:
    ov_json_rpc_api.logger:
        alias: App\Infrastructure\Logger\MyCustomLogger
```

If both are set, the alias in `services.yaml` wins (the project's `services.yaml` is resolved before the bundle extension is compiled, and the alias declared there is applied on top of the compilation result — the details and the full precedence table are below, under "Precedence of the override mechanisms"). The service must implement `Psr\Log\LoggerInterface`. The bundle still takes care of pairing, masking, body truncation and building message/context/level through `JsonRpcLogFormatterInterface`; your logger receives the final `log($level, $message, $context)` with all of its own handlers and processors.

### Splitting by Monolog channel

Symmetrically — name the Monolog channel's service:

```yaml
ov_json_rpc_api:
    logging:
        logger_service: monolog.logger.json_rpc_api
```

## Replacing JsonRpcCallLoggerInterface entirely

To bypass the bundle's whole high-level logic — pairing, encoding, masking, the formatter — implement `JsonRpcCallLoggerInterface` yourself and replace the default.

**Through `call_logger_service`:**

```yaml
ov_json_rpc_api:
    logging:
        enabled: true
        call_logger_service: App\Logging\MyJsonRpcCallLogger
```

**Through an alias in `services.yaml`:**

```yaml
services:
    OV\JsonRPCAPIBundle\Core\Logging\JsonRpcCallLoggerInterface:
        alias: App\Logging\MyJsonRpcCallLogger
```

`logging.enabled: false` is a kill switch **only while you have not declared an alias by hand in `services.yaml`**: an alias on `JsonRpcCallLoggerInterface` in the project's `services.yaml` wins even against `enabled: false`. If both the config key (`call_logger_service`) and the alias are set, the alias wins. See "Precedence of the override mechanisms" below.

The interface contract (`logRequest` / `logRawRequest` / `logResponse`) is in `src/Core/Logging/JsonRpcCallLoggerInterface.php`. With an implementation of your own the bundle does not use `JsonRpcLogFormatterInterface`, `SensitiveDataMaskerInterface` or `ContextIdGeneratorInterface` — wire them in or not, as you see fit.

## Precedence of the override mechanisms

> **Important if you rely on `logging.enabled: false` as a guaranteed way to switch logging off:** an alias declared in your own `config/services.yaml` beats **everything**, the kill switch included. Below is the actual order, verified by an integration test against a real `ContainerBuilder` rather than by assumption about how Symfony DI ought to behave.

The reason lies in the mechanics of Symfony DI: the project's `config/services.yaml` is processed and lands in the container before the bundle extension is compiled. `MergeExtensionConfigurationPass` records the container's existing definitions and aliases **before** calling `OVJsonRPCAPIExtension::load()`, and after merging the extension's results it lays those original aliases back on top — so whatever is written explicitly in your `services.yaml` always has the last word, whatever the bundle decided to do.

The PSR-3 logger (`ov_json_rpc_api.logger`):

1. **An `ov_json_rpc_api.logger` alias in your `services.yaml` always wins**, even when the configuration says nothing.
2. `logging.logger_service` (config) — applied when the alias in `services.yaml` is not overridden.
3. The default — `@logger`, registered by the bundle's own `config/services.yaml`.

`JsonRpcCallLoggerInterface`:

1. **A `JsonRpcCallLoggerInterface` alias in your `services.yaml` always wins**, even over `logging.enabled: false`. If you declared an alias to your own logger by hand, `enabled: false` will not switch it off — remove the alias from `services.yaml` if you want a real kill switch.
2. `logging.enabled: false` → `NullJsonRpcCallLogger`, the kill switch, when no alias is set in `services.yaml`.
3. `logging.call_logger_service` (config) — applied when `enabled: true` and no alias is set in `services.yaml`.
4. The default — `JsonRpcCallLogger`, under `enabled: true`.

In other words: `services.yaml` is a manual, explicit override that stands **above** both the config keys and the kill switch. The config keys (`logger_service` / `call_logger_service`) are the way to replace the sink without touching `services.yaml` by hand; `logging.enabled` is the default switch for when neither is set.

## Performance

At `enabled: false` the whole subsystem is a single Null Object with three no-op methods. The overhead of disabled logging is negligible — one method call per Request/Response, delegating to an empty body the JIT inlines.

At `enabled: true` the main cost is `json_decode`, a recursive walk for masking, and `json_encode`. For typical JSON-RPC bodies that is under 1 ms. If log volume is a concern, set `max_body_length` to truncate large payloads.
