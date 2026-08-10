[English](upgrade-5.0.md) · [Русский](upgrade-5.0.ru.md)

# Upgrade Guide: 4.x → 5.0


5.0 is a spec-conformance release. A permanent test suite written against the letter of the [JSON-RPC 2.0 spec](https://www.jsonrpc.org/specification) found seventeen places where the bundle's behaviour diverged from the protocol; sixteen are fixed here. Nearly every change is BC-breaking, because correcting a deviation from the spec changes observable behaviour by definition.


## TL;DR

Unlike 4.0, there is **no single YAML switch that restores the old behaviour**. Most of what changed — serialising only what a class makes public, exact DTO name binding, batch detection by container shape, id/notification semantics, `-32000` instead of HTTP 403, the mandatory `Content-Type` — is protocol and data-handling behaviour, not a tuning knob. The one exception used to be a config key: `cors_strict` is **removed without a replacement**, and the legacy comma-joined CORS mode no longer exists at any setting.

```bash
composer require otezvikentiy/json-rpc-api:^5.0
./vendor/bin/phpunit tests/   # run your integration suite first
```

Update the dependency, run your tests, read the output, then work through the list below item by item. Each change is a bug in the previous version corrected against the spec; "rolling back" generally means returning to a protocol violation, so migration is a matter of adapting calling code rather than adjusting configuration.

## BC-breaking changes

### 1. `Content-Type: application/json` is required

**4.x:** the request body was parsed as JSON regardless of the `Content-Type` header.
**5.0:** requests with a body (POST/PUT/PATCH/DELETE) that do not carry `Content-Type: application/json` (an optional `; charset=...` is fine) are rejected with `-32600 Invalid Request`, even when the body is valid JSON. Form-encoded (`application/x-www-form-urlencoded`) and `multipart/form-data` are refused on the same grounds.

**What breaks:**
- Clients that do not set `Content-Type` explicitly — some HTTP libraries default to `text/plain` or to nothing at all.
- Forms submitting JSON-RPC calls as `application/x-www-form-urlencoded`.

**What to do:**
- Make sure every client sets `Content-Type: application/json` on requests with a body.
- If you accepted form-encoded deliberately, that is no longer supported. The check closes a CSRF vector — form-encoded is a "simple request" under the CORS spec, so a cross-origin form could call your methods as the logged-in user with no preflight — and it closes a way around `max_payload_bytes`.

### 2. Scalar parameters are no longer coerced

**4.x:** PHP's weak typing turned `"42"` into `int 42` on the way through the setter.
**5.0:** `"42"` for an `int` field is `-32602 Invalid params`. Validation runs through Symfony Validator with `Assert\Type` and no coercion, and any `TypeError` originating in client input at any level — DTO constructor, setter, adder, nested DTO — becomes `-32602` rather than `-32603` or an unhandled exception.

**What breaks:**
- Clients sending numbers as strings, common with form-data-style SDKs or JS `URLSearchParams`.
- Test fixtures using `"1"` / `"0"` instead of `true` / `false` for boolean fields.

**What to do:**
- Send values of the correct JSON type: numbers unquoted, booleans as `true` / `false`.

### 3. Responses serialise what the class makes public

**4.x:** response serialisation read properties straight off the object with `ReflectionProperty::getValue()` - private ones included.
**5.0:** a value reaches the JSON when the class exposes it: through a public getter (`getFoo()`, `isFoo()`, `foo()`) or by being a public property. The line is visibility, not ceremony.

A private or protected property with no public getter never reaches the payload, and that was the defect: a DTO holding a `passwordHash` or an internal token handed it to the client, because Reflection reads a private property as happily as a public one. A private getter does not qualify either - nothing outside the class can call it.

**What breaks:**
- Response classes with **private** fields and no public getter - those fields disappear. If a field belongs in the response, give it a getter or make the property public.
- A getter named after something other than its property (`private array $items` with `getItemsList()`) does not satisfy the exact-name rule; rename it to `getItems()`.

**What to do:**
- Go through your response DTOs and decide, for each private field without a getter, whether it should reach the client. The ones that should not are the reason for this change.
- Public properties, promoted constructor parameters among them, need no attention: they serialise as before.

### 4. A cyclic object graph in a response is `-32603`, not a dead worker

**4.x:** a bidirectional relation (`Order` → `User` → their `Order`s) recursed until the stack overflowed and the worker segfaulted — no response, no log entry.
**5.0:** cycle detection (an `SplObjectStorage` visited set) raises `JRPCException` with `-32603 Internal error`, the client gets a well-formed JSON-RPC response, and `ErrorSanitizer` logs it.

A **nesting ceiling of 64 levels** comes with it. Cycle detection works on object identity, and a self-referencing array (`$a['self'] = &$a`) has none: arrays in PHP are values, and `SplObjectStorage` cannot tell them apart. The same ceiling applies in `JsonRpcRequest::toArray()`.

**What breaks:** nothing the client could observe, because the server used to die. One separate case: a response that is **acyclic but deeper than 64 levels** is now refused with `-32603 Value nesting is too deep to serialise.` It used to serialise.
**What to do:** if your DTO graph really is cyclic by design, break the cycle at the DTO level — for instance, leave the back-reference out of the response object. If a response is legitimately deeper than 64 levels, note that such a structure could not have been accepted on the way in either (`max_json_depth` also defaults to 64), which is why the ceiling is not configurable; reshape the response.

### 5. `DateTimeInterface` is serialised as ISO 8601

**4.x:** `BaseResponse` had no special handling for dates — a `DateTime` or `DateTimeImmutable` fell apart into an empty array of internal properties instead of a string.
**5.0:** any `DateTimeInterface`, `DateTimeImmutable` included, is formatted as ISO 8601 (`DATE_ATOM`), both in responses and in `JsonRpcRequest::toArray()`.

**What breaks:** clients that had somehow accommodated an empty `{}` where a date belonged. Unlikely, but if such code exists it stops receiving `{}`.
**What to do:** nothing — this is a plain bug fix. If you formatted dates by hand with a getter such as `getCreatedAtFormatted(): string` to work around it, you may now simplify to `getCreatedAt(): DateTimeImmutable`.

### 6. DTO methods bind to properties by exact name

**4.x:** getters were resolved with `str_contains()`, so `getUserId()` could satisfy both the `userId` property and the `id` property. Setters were registered by **parameter** name rather than property name.
**5.0:** exact match on the property name. Collection adders resolve through `Symfony\Component\String\Inflector\EnglishInflector` (`children` → `addChild`) instead of dropping the last letter (`children` → `childre`, which silently added nothing).

**What breaks:**
- DTOs with property pairs where one name is a substring of another (`id` / `userId`, `token` / `refreshToken`, `name` / `fullName`): the validator could quietly have been checking the wrong field.
- DTOs with irregular plurals in a collection name (`children`, `people`, `data`), where the adder silently failed to resolve and the collection stayed empty with no error. These now either resolve correctly or fail at container compile time (`Property %s has no accessible getter`) rather than staying quiet at runtime.

**What to do:** review DTOs with similar property names and with irregular collection plurals. A compile-time failure about a missing getter is most likely a bug that surfaced, not one that was introduced — it was previously masked by the loose binding.

### 7. `allow_extra_fields` applies identically at every depth

**4.x:** the flag was checked once at the top level; recursive hydration of nested DTOs rejected extra fields unconditionally, even with `allow_extra_fields: true`.
**5.0:** the same flag (`allow_extra_fields` globally, `allowExtraFields` per method) applies at every level of recursion.

**What breaks:** if you relied on `allow_extra_fields: true` permitting extra fields only at the top level while nested DTOs still rejected them — an unlikely but possible edge case — nested DTOs now permit them too.
**What to do:** usually nothing; this aligns behaviour with what the flag says.

### 8. Batches are detected by container shape

**4.x:** an array counted as a batch only if its first element was a valid request object (`jsonrpc` + `method`). An invalid first element dropped the whole thing back to single-request handling, and every valid call further along the array was **lost with no error at all**.
**5.0:** any non-empty JSON list (`array_is_list()`) is a batch, regardless of whether its elements are valid. `[{"foo":"boo"}, {valid request}]` now returns an array carrying an error for the first and a result for the second.

**What breaks:** clients relying — even accidentally — on the old behaviour of discarding a whole batch when the first element was invalid.
**What to do:** nothing in particular; the new behaviour is strictly more useful. Check integration tests that pinned the old discarding.

### 9. `{"id": null}` is a Request; a Notification never gets a response

**4.x:** `isset($data['id'])` could not distinguish "there is no `id` field" from "`id` is present and null", and treated both as a Notification. A single Notification whose `call()` returned something non-empty answered with `"{}"` instead of an empty body — the server contradicting itself, since `"{}"` is a response and a Notification must not receive one.

**5.0:** `BaseRequest` tracks the presence of the `id` key separately from its value. `{"id": null}` is a full Request and always gets a response, carrying `"id": null`. An absent `id` is a Notification, which never gets a response — including when `call()` threw. The single exception is an envelope damaged badly enough that whether it was a Notification cannot be established; that case still gets diagnostics.

**What breaks:**
- Clients sending `{"id": null}` and expecting silence. Non-standard, but it used to work.
- Clients sending Notifications and receiving `"{}"` instead of an empty body.

**What to do:** if you want a Notification, omit `id` entirely rather than sending `"id": null`.

### 10. `params: null` is rejected

**4.x:** `isset($data['params'])` let an explicit `"params": null` through, treating it as "no parameters".
**5.0:** an explicit `null` in `params` is `-32600 Invalid Request`, as section 4 requires — `params`, when present, must be a structured value.

**What breaks:** clients that send `"params": null` explicitly instead of omitting the member.
**What to do:** omit `params` when there are no parameters; do not send `null`.

### 11. A role denial is `-32000` in a JSON-RPC error object, not HTTP 403

**4.x:** `RequestHandler` returned a `JsonResponse` holding the string `"Access not allowed"` with HTTP 403 — not a JSON-RPC object. Inside a batch this broke the structure: the bare string was spliced into the array of responses as-is, the `id` was lost, and the 403 disappeared because the whole batch response was reassembled with status 200.
**5.0:** a denial raises `JRPCException` with code `-32000` and takes the ordinary error path — a correct object with the right `id` and HTTP 200, working the same alone and inside a batch.

**What breaks:** monitoring that counts HTTP 403 as a proxy for role denials.
**What to do:** read `error.code === -32000` instead of the HTTP status. See [security/roles.md](./security/roles.md).

### 12. Error codes are normalised into the allowed range

**4.x:** an arbitrary `Throwable` with code `0` — or any other value outside the standard codes and `-32000..-32099` — reached the client as-is when `expose_internal_errors: true`.
**5.0:** a code outside the permitted ranges is replaced with `-32603`.

**What breaks:** code parsing `error.code` from non-`JRPCException` throwables with `expose_internal_errors: true` and expecting arbitrary values — a non-standard setup.
**What to do:** usually nothing; `expose_internal_errors: true` is only recommended for development anyway.

### 13. OpenAPI schema names changed

**4.x:** a schema was named after the short class name. Projects placing response and request classes in `RPC/V<n>/<Method>/Response.php` — a common layout, see [the file structure](./examples/base.md) — ended up with dozens of classes all short-named `Response`, silently overwriting one another under a single `components/schemas` key.
**5.0:** the response schema is `{methodName}Response`, the parameter schema is `{methodName}Request`, and nested DTOs use the fully qualified class name (`\` → `.`).

**What breaks:** tooling that generated client code from the old schema names (`Response`, `Request`, `Product`).
**What to do:** regenerate Swagger (`bin/console ov:swagger:generate`) and any client code that depends on exact schema names.

### 14. `cors_strict` is removed

**4.x:** `cors_strict: true` (the default) meant strict behaviour; `false` produced a legacy comma-joined header.
**5.0:** the key is **removed with no replacement**. Behaviour is always what `cors_strict: true` used to give. A `cors_strict` key left in the config is a container compilation error (`InvalidConfigurationException`), not an ignored value.

**What breaks:** configs with an explicit `cors_strict: true` or `cors_strict: false` — both stop the application at boot.
**What to do:** delete the `cors_strict` line from `config/packages/ov_json_rpc_api.yaml`. If you relied on `cors_strict: false` and its comma-joined header, that behaviour no longer exists at any setting; list the origins you mean in `access_control_allow_origin_list`.

### 15. CORS preflight (`OPTIONS`) is handled by the bundle

Not BC-breaking for existing clients, but it changes the response to `OPTIONS /api/v{version}`: previously a 404 or 405 unless you configured preflight separately in nginx or a CORS bundle, now `204 No Content` with the full set of CORS headers. If you did configure preflight separately, it is most likely redundant now; see [cors.md](./cors.md).

A new key, `cors_allowed_headers` (default `['Content-Type']`), lists the headers the preflight response permits — extend it if your frontend sends custom headers such as `X-AUTH-TOKEN`.

### 16. Dependency bounds in the manifest

**4.x:** `composer.json` declared dependencies with no upper bound (`>=X`), including `php: ">=8.2"`.
**5.0:** `symfony/*: ^6.4 || ^7.0 || ^8.0` and `php: 8.2.* || 8.3.* || 8.4.* || 8.5.*`. `doctrine/annotations` is dropped from require. Every Symfony component the source actually imports, along with `psr/log`, is now declared directly instead of arriving transitively.

The upper bound states what CI verifies rather than what happens to install: the matrix runs ten combinations of PHP 8.2/8.3/8.4/8.5 against Symfony 6.4/7.x/8.x, minus Symfony 8 below PHP 8.4 - a combination that cannot exist, since Symfony 8 requires PHP >= 8.4.1.

**What breaks:** `composer require` can no longer quietly pull in an untested Symfony 8 or PHP 9. If you are already on one of those, upgrading to 5.0 means aligning your infrastructure versions first.
**What to do:** usually nothing — the upper bounds only state what the bundle is actually tested against. If you relied on `doctrine/annotations` arriving transitively through this bundle, declare it in your own project.

### 17. The container is no longer aliased into applications, and RPC methods are private

**4.x:** the bundle's `config/services.yaml` aliased `Symfony\Component\DependencyInjection\Container` to `'@service_container'`, so any service in the application could obtain the entire container simply by type-hinting it. That alias was also the reason every RPC method had to be registered as a public service.
**5.0:** the alias is gone and RPC methods are private. `RequestHandler` receives a `ServiceLocator` built from the `ov.rpc.method` tag.

**What breaks:** a service of yours declaring `__construct(private Container $container)` stops compiling — `Cannot autowire service ...: argument $container of type Symfony\Component\DependencyInjection\Container`. Reaching an RPC method through the production container (`$container->get(MyMethod::class)`) no longer works either — `ServiceNotFoundException: ... has been removed or inlined`.
**What to do:** **there is deliberately no replacement for the alias** — inject the services you need instead of the whole container. Test access to private services is unaffected: `KernelTestCase` plus `self::getContainer()->get(MyMethod::class)` works as before, because the test container exposes private services.

Thirty classes are marked `@internal` along with this: all of `src/DependencyInjection/` except `Configuration` and `OVJsonRPCAPIExtension` (which are where an application supplies configuration), all of `src/Swagger/`, the serialisation trait, and the request-processing pipeline — `ApiController`, `RequestHandler`, `RequestRawDataHandler`, `ResponseService`, `HeadersPreparer`, `ErrorSanitizer`, `BatchStrategyFactory`, both batch strategies and `HandleBatchInterface`. In production code an application constructs none of them; the container does. The marking is what buys the freedom to rework them inside 5.x rather than waiting for the next major. Tests are the exception: the harness in [testing.md](./testing.md) assembles part of the stack by hand and **describes `MethodSpec` manually**, and `MethodSpec` with its metadata is marked internal too. `MethodSpecCollection::getMethodNames()` is removed — it returned API version numbers rather than method names, and its only caller was its own test.

What did **not** get the marking, and remains public contract: `ApiMethodInterface`, `PreProcessorInterface`, `PostProcessorInterface`, `PlainResponseInterface`, `OvResponseInterface`, `PartialRequestInterface`, `JsonRpcRequest`, `BaseRequest`, `BaseResponse`, `JRPCException`, the `#[JsonRPCAPI]` attribute, the logging interfaces and the configuration keys.

The practical consequence: if your test harness builds `RequestHandler` / `ResponseService` / `HeadersPreparer` by hand, or describes `MethodSpec` manually as [testing.md](./testing.md) shows, it leans on internals deliberately and their signatures may change in a 5.x minor. That is an acceptable trade — a fast test without booting a kernel — but it is a trade, not free speed. An integration test through `KernelTestCase` is unaffected: there the container assembles both the stack and the `MethodSpec`.

### 18. Log masking is on by default

**4.x:** `logging.masking.key_patterns` was an empty list and `logging.max_body_length` was `0`, meaning no truncation. A single `logging.enabled: true` was enough to write complete request and response bodies to the log, passwords included.
**5.0:** `key_patterns` ships twenty-nine patterns (`password`, `token`, `secret`, `authorization`, `jwt`, certificate keys and more) and `max_body_length` is `8192`.

**What breaks:** if logging is already on, placeholders (`***`) appear where values used to be and long bodies are truncated. Dashboards and alerts that parse fields out of logged bodies may stop finding them. An invalid regular expression in `key_patterns` now fails container compilation — previously it silently disabled masking for that pattern and announced it with one warning per process lifetime.
**What to do:** if a field is masked needlessly, narrow `key_patterns` to your own list; if you need longer bodies, raise `max_body_length`. Do not go back to `key_patterns: []` — that is exactly the configuration that put passwords in the log. Note that supplying your own list **replaces** the defaults rather than adding to them, and avoid anchored patterns: `~^password$~i` matches neither `user_password` nor `pwd_hash`, which is what the shipped patterns are unanchored to catch. Full parameter table in [logging.md](./logging.md).

### 19. An `id` that is a boolean, an array or an object is rejected

**4.x:** any value in the `id` field was accepted and echoed back — `{"id": true}` returned `"id": true`, `{"id": ["a"]}` returned the array.
**5.0:** section 4 allows only String, Number and Null. Anything else is `-32600 Invalid Request`, answered with `"id": null` as section 5 requires when the id could not be established.

**What breaks:** a client using a composite `id` — an object carrying correlation metadata, rare but it happens — gets a refusal instead of a response.
**What to do:** send the `id` as a string or a number. If you need structure, pack it into a string on the client side.

### 20. Well-formed JSON that is not an object is `-32600`, not `-32700`

**4.x:** a body of `42`, `"a string"`, `true` or `null` answered `-32700 Parse error`.
**5.0:** `-32600 Invalid Request`. Section 5.1 reserves `-32700` for text that **failed to parse** as JSON; this parsed perfectly well, it simply is not a Request object.

**What breaks:** monitoring that distinguishes the two codes sees a shift between them. Invalid JSON is still `-32700`.
**What to do:** nothing, unless you depend on a specific code for this input.

### 21. The GET branch applies payload limits

**4.x:** `max_payload_bytes` and `max_json_depth` were checked against the request body only. Moving the payload into the query string bypassed both.
**5.0:** the same limits apply to the GET branch. Size is measured against the raw `QUERY_STRING` rather than the normalised one, so duplicate keys cannot understate it.

**What breaks:** a GET request with a very long query string that used to pass now receives `-32600` with `Query string size exceeds limit`.
**What to do:** usually nothing. If you have a GET method with a large parameter set, either raise `max_payload_bytes` or move the method to POST.

**One asymmetry worth knowing:** on the GET branch `max_json_depth` is effectively capped by `max_input_nesting_level` from `php.ini` (64 by default) — PHP parses the query string before the bundle gets control and truncates deeper structures itself. On the POST branch the limit is honoured in full.

### 22. An exception from a PostProcessor no longer propagates

**4.x:** the `finally` block in `RequestHandler::processBatch()` was not wrapped in anything, so an exception from a PostProcessor — or from writing the response to the log — escaped and turned into an error response.
**5.0:** the whole block is wrapped in `try/catch (Throwable)`; the failure is written to the logger as `JSON-RPC post-response stage failed` and does not affect the response.

The reason: `finally` runs **after** the response has been formed, and in batch mode after each element. An exception from there overwrote a finished result with an error, and inside a batch it could abort the processing of remaining elements that had nothing wrong with them.

**What breaks:** a PostProcessor that threw on purpose — to fail an audit, to refuse to release a response, to roll back a transaction — no longer affects anything. The client receives a successful response and the refusal survives only as a log line.
**What to do:** if a PostProcessor decides whether a response is released, move that logic into the method itself or into a PreProcessor — somewhere the decision is taken before the response exists. If your PostProcessor only writes logs, emits metrics or releases resources, do nothing: that is the case this isolation exists for.

### 23. Scalars from a query string are read as the declared type

**4.x:** PHP's weak typing coerced query-string values on the way through the setter - incidentally and beyond anyone's control.
**5.0:** the conversion is explicit and confined to the GET branch. A query string carries no types by nature - PHP parses one into strings - so a value is read the way the declared property type asks:

```
GET  ?params[id]=5&params[active]=true   ->  int 5, bool true
POST {"params":{"id":"5"}}               ->  -32602, a string where an int belongs
```

The distinction is whether the caller had any way to express the type. JSON has types, so `"42"` instead of `42` is a real mistake (item 2). A query string offers nothing to get wrong.

`int`, `float` and `bool` are read; strings stay strings. Only unambiguous representations are recognised - booleans use PHP's own filter, so `1/true/on/yes` and `0/false/off/no`, and numbers must be numbers in full. Anything else is passed through untouched and refused by the validator exactly as before: `?params[id]=abc` is still -32602, and so is `1.5` for an int. The conversion applies at every depth: nested DTOs, collection elements and constructor parameters.

**What breaks:** nothing that worked. GET methods with `int`, `bool` or `float` fields worked in 4.x through weak typing, stopped working in early 5.0 builds, and work again now - this time by design.
**What to do:** nothing.

## What does not break

- The `#[JsonRPCAPI]` attribute — every parameter and the versioning semantics are unchanged.
- `ApiMethodInterface` and the `call()` signature.
- `JRPCException` — all codes and the API, the constructor, `additionalInfo`.
- `PartialRequestInterface`, `PartialUpdateRequest`, `TracksProvidedFieldsTrait` and the `wasProvided()` semantics.
- Pre/post processors — signatures and call order are unchanged. **Their error handling is not:** see item 22.
- `strict_notifications`, `expose_internal_errors`, `allow_extra_fields`, `max_payload_bytes`, `max_batch_size`, `max_json_depth`, `max_dto_depth`, `max_array_param_size` — same keys, same defaults as in 4.x. The exception is `logging.max_body_length`, whose default changed; see item 18.
- The error shape `{jsonrpc, error: {code, message}, id}` is unchanged.
- Extension points — `ApiMethodInterface`, `PreProcessorInterface`, `PostProcessorInterface`, `PlainResponseInterface`, `PartialRequestInterface`, `JsonRpcRequest`, `BaseResponse`, `JRPCException` and the logging interfaces — did not get the `@internal` marking and remain part of the public contract.

> **A caveat on that last point.** "Remains public contract" is a statement about the marking, not about behaviour being unchanged. Two of them did change: `BaseResponse` serialises what the class makes public (item 3), and `JsonRpcRequest::toArray()` now does the same and can throw `JRPCException` on a cyclic graph — see the BC-breaking section of [CHANGELOG.md](../CHANGELOG.md) and [json_rpc_request.md](./json_rpc_request.md). The others changed in neither signature nor behaviour.

## Upgrade checklist

1. `composer require otezvikentiy/json-rpc-api:^5.0`.
2. Remove `cors_strict` from your config if it is there, or the container will not compile.
3. Run your tests. Most failures will be about strict typing, private fields no longer serialised, or changed OpenAPI schema names.
4. Go through your clients: is `Content-Type: application/json` set on every request with a body, and are any parameters being sent as stringified numbers?
5. Check response DTOs for **private** properties with no public getter - those stop reaching the client (item 3). Public properties need no attention.
6. Regenerate Swagger and any OpenAPI client code — the schema names changed.
7. If you monitor role denials by HTTP status, switch to `error.code === -32000`.
8. Review DTOs with similar property names (`id` / `userId`) and irregular collection plurals (`children`, `people`) — these could previously have masked a bug.
9. Search your code for constructors type-hinted as `Container` and for RPC methods fetched from the production container; neither compiles any more (item 17).
10. If logging is enabled, check `key_patterns` and `max_body_length` against your expectations — masking now works out of the box (item 18).
11. Check whether any client sends a composite `id` (an object or an array); such requests are now rejected (item 19).
12. If a PostProcessor of yours throws on purpose, that logic no longer affects the response — move it into the method or a PreProcessor (item 22).
13. GET methods with `int`, `bool` or `float` fields keep working — query-string values are read as the declared type (item 23). Nothing to do.

## Rolling back

Unlike 4.0, there is no "restore the old behaviour with one config key" for 5.0: most of these fixes are protocol behaviour rather than switchable settings. If you genuinely need to roll back for now, pin the dependency to the latest `4.x` (`composer require otezvikentiy/json-rpc-api:^4.2`) and migrate through this guide as a separate step rather than through configuration.
