[English](batch.md) · [Русский](batch.ru.md)

# Batch requests

The bundle supports the JSON-RPC 2.0 batch format — an array of request objects in one HTTP request. Each element is handled separately, and the response is an array of separate responses.

A batch is recognised by the **shape of the container**, not by the contents of its first element: any non-empty JSON array that is a list (`array_is_list()`) is handled as a batch, even when some or all of its elements are invalid as JSON-RPC requests. So `[{"foo": "boo"}, {a valid request}]` returns an array with an error for the first element and a result for the second — rather than silently losing the valid call, as it would if the first element decided everything.

## A basic example

```bash
curl -X POST http://localhost/api/v1 \
  -H "Content-Type: application/json" \
  -d '[
    {"jsonrpc": "2.0", "method": "sum",    "params": [1, 2, 4], "id": "1"},
    {"jsonrpc": "2.0", "method": "notify_hello", "params": [7]},
    {"jsonrpc": "2.0", "method": "subtract", "params": [42, 23], "id": "2"}
  ]'
```

The response:
```json
[
    {"jsonrpc": "2.0", "result": {"result": 7}, "id": "1"},
    {"jsonrpc": "2.0", "result": {"result": 19}, "id": "2"}
]
```

`notify_hello` is a notification (it has no `id`) and receives no response in strict mode.

> **Why `result` is nested.** The specification does not prescribe the shape of `result` — whatever the method returned goes there. The method returns a response DTO, and the bundle serialises it through its public getters, so `result` holds an object with that DTO's properties. In the examples above the DTO has one property, also named `result`, hence `{"result": {"result": 7}}`. If a response DTO declares `success`, `title` and `price`, those are what `result` holds; a worked example is in the [README](../README.md).

The nesting comes from the DTO, not from the protocol: a `call()` returning a scalar gives `{"result": 7}` with no wrapper. The shape of `result` is decided by what your method returns — and returning a response DTO is the usual way, and the one every example in this documentation uses.

## Batch size

Since 4.0 a `max_batch_size` limit applies (default 50). Exceeding it rejects the whole batch with a single response:

```json
{
    "jsonrpc": "2.0",
    "error": {
        "code": -32600,
        "message": "Invalid Request. Additional info: Batch size 51 exceeds limit 50."
    },
    "id": null
}
```

Why the limit exists: every request is handled **sequentially** — there is no internal parallelism. Without a limit, one HTTP request carrying 100,000 elements could run for minutes and exhaust memory, which is a denial-of-service vector. Choose `max_batch_size` against:

- the profile of your methods (if they are all fast, 100–200 is fine; if any is heavy, 10–20);
- the latency SLA of the endpoint;
- the memory budget of a PHP process.

## Order of processing

Requests are handled sequentially, in array order. Responses come back **in the same order** the requests arrived. Identification is through the `id` field:

```json
[
    {"jsonrpc": "2.0", "method": "a", "id": 1},
    {"jsonrpc": "2.0", "method": "b", "id": 2}
]
```

The guaranteed response:
```json
[
    {"jsonrpc": "2.0", "result": ..., "id": 1},
    {"jsonrpc": "2.0", "result": ..., "id": 2}
]
```

## A batch of nothing but notifications

If every element of a batch is a notification (has no `id`), the server **must** return nothing. The bundle returns HTTP 200 with an empty body.

```json
[
    {"jsonrpc": "2.0", "method": "log_event", "params": [...]},
    {"jsonrpc": "2.0", "method": "log_event", "params": [...]}
]
```
→ HTTP 200, body = `""`

## A mixed batch

When requests and notifications are mixed, the response contains only the requests.

```json
[
    {"jsonrpc": "2.0", "method": "sum", "params": [1,2], "id": 1},
    {"jsonrpc": "2.0", "method": "log_event"}
]
```
→ the response holds only the result of `sum`; the notification runs but is not returned.

## Errors inside a batch

An error in one request does not stop the others. Every request carrying an `id` gets its own response — success or error, independently:

```json
[
    {"jsonrpc": "2.0", "method": "nonexistent", "id": 1},
    {"jsonrpc": "2.0", "method": "sum", "params": [1,2], "id": 2}
]
```
→
```json
[
    {"jsonrpc": "2.0", "error": {"code": -32601, "message": "Method not found"}, "id": 1},
    {"jsonrpc": "2.0", "result": {"result": 3}, "id": 2}
]
```

## Edge cases

### An empty array

```json
[]
```
→ `INVALID_REQUEST` (-32600). By the specification an empty batch is an invalid request.

### A batch of one

```json
[{"jsonrpc": "2.0", "method": "sum", "id": 1}]
```
→ handled as a single-element batch (the factory tells an array-of-arrays from a lone object). The response is an array with one element.

### Invalid JSON

```
[{"jsonrpc": "2.0", "method": ...
```
→ `PARSE_ERROR` (-32700); the whole batch is rejected.

## Transactionality

The bundle **offers no transactional guarantees** for batches. If request #3 fails, requests #1 and #2 have already run. When you need all-or-nothing, write a single "business method" that accepts an array of operations and rolls everything back on failure.

## Pre- and post-processors

Pre- and post-processors are invoked for each request in a batch independently. There is no global "whole batch" processor.

## Related

- [security_hardening.md](./security_hardening.md) — `max_batch_size` and the other limits
- [notifications.md](./notifications.md) — strict versus lenient mode
- [errors.md](./errors.md) — error codes and their shape
