[English](notifications.md) · [Русский](notifications.ru.md)

# Notification requests

## What a Notification is

By the [JSON-RPC 2.0 specification](https://www.jsonrpc.org/specification#notification), a Notification is a request **without an `id` field**. The client sends it and expects no reply from the server.

```json
{"jsonrpc": "2.0", "method": "notify_hello", "params": [7]}
```

Note that the `id` field is absent entirely — which is not the same as `"id": null`. A request with an explicit `"id": null` is a **full request**, not a Notification: it always receives a response (carrying `"id": null`), regardless of `strict_notifications`.

```json
{"jsonrpc": "2.0", "method": "subtract", "params": [42, 23], "id": null}
```

**The response, always, even under `strict_notifications: true`:**
```json
{"jsonrpc": "2.0", "result": {"result": 19}, "id": null}
```

---

## Configuring the behaviour

What the bundle does with a Notification is governed by `strict_notifications`:

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    strict_notifications: true  # the default
```

### `strict_notifications: true` (strict mode, the default)

Full conformance with the JSON-RPC 2.0 specification. The server **returns no response** to a Notification, even when `call()` produced a non-empty result — and even when `call()` threw. The one exception: if the request envelope is damaged badly enough that the bundle cannot establish whether it was a Notification at all (invalid JSON, for instance), the diagnostic is sent anyway — there is no way to know whether the sender was expecting a reply.

**Request:**
```bash
curl -X POST http://localhost/api/v1 \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc": "2.0", "method": "notify_hello", "params": [7]}'
```

**Response:** empty (HTTP 200 with an empty body).

### `strict_notifications: false` (lenient mode, opt-in)

A gentler mode for convenience during development. If the method returned a non-empty result, the response is sent to the client even though the request was a Notification (had no `id`). The response carries `id` as `null`.

**Request:**
```bash
curl -X POST http://localhost/api/v1 \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc": "2.0", "method": "subtract", "params": [42, 23]}'
```

**Response:**
```json
{
    "jsonrpc": "2.0",
    "result": {"result": 19},
    "id": null
}
```

---

## Notifications inside a batch

Within a batch, Notification elements are processed but left out of the response array:

**Request:**
```json
[
    {"jsonrpc": "2.0", "method": "sum", "params": [1, 2, 4], "id": "1"},
    {"jsonrpc": "2.0", "method": "notify_hello", "params": [7]},
    {"jsonrpc": "2.0", "method": "subtract", "params": [42, 23], "id": "2"}
]
```

**Response (under `strict_notifications: true`):**
```json
[
    {"jsonrpc": "2.0", "result": {"result": 7}, "id": "1"},
    {"jsonrpc": "2.0", "result": {"result": 19}, "id": "2"}
]
```

The `notify_hello` element ran, but does not appear in the response.

---

## When to use them

- **Event logging** — the client does not need the result.
- **Sending notifications** — fire and forget.
- **Updating statistics** — a background operation with nothing to wait for.

---

## Recommendation

`strict_notifications: true` (the default) matches the specification and reduces traffic; do not change it without a good reason. The `false` mode can be turned on deliberately during development and debugging, when seeing a response even to a Notification is convenient.
