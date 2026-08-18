[English](profiler.md) · [Русский](profiler.ru.md)

# Symfony Profiler

When the application runs with `kernel.debug: true` and `WebProfilerBundle` enabled, the bundle adds a **JSON-RPC** item to the Symfony toolbar and profiler.

The panel has two views:

- **Calls** shows the inbound calls handled by the current HTTP request: method, JSON-RPC id, masked parameters and response, result/error status, error code, duration, and the logging context id. A JSON-RPC batch is one profiler row with its calls as children.
- **Registered methods** reads the bundle's compiled method specifications. It shows every version and method together with its handler, request DTO, parameters, required/optional status, summary, tags, group, and roles.

No configuration is required. Profiler collection is independent of `logging.enabled`: disabling PSR-3 call logs does not make the development panel empty. The traceable logger decorates the configured call logger, so enabling logs still produces the same log entries and context ids.

## Sensitive values

Profiler request and response data goes through the same configured `SensitiveDataMasker` as call logging. The default key patterns therefore hide passwords, tokens, secrets, and similar values. Uploaded files are represented only by original name, size, and MIME type; file content and temporary paths are never stored.

Malformed raw bodies are represented only by their byte length. Their content is not retained by the collector.

## Availability and cost

The traceable logger and data collector services exist only when both conditions are true:

1. `kernel.debug` is enabled.
2. `WebProfilerBundle` is registered.

They are removed from the container otherwise, so production requests pay no collection cost. The profiler currently records inbound calls only; outbound JSON-RPC traffic is outside its scope.
