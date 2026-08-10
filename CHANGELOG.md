[English](CHANGELOG.md) · [Русский](CHANGELOG.ru.md)

# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [5.0] - 2026-08-07

A specification-conformance release: a permanent suite written against the
[JSON-RPC 2.0 spec](https://www.jsonrpc.org/specification) found seventeen
deviations, sixteen of which are fixed here. Detailed migration guide —
[docs/upgrade-5.0.md](./docs/upgrade-5.0.md).

### Added
- **Permanent conformance suite** — thirty-nine tests covering sections 4, 4.1, 4.2, 5, 5.1 and 6 of the specification, including every example from the Examples section. A regression against the spec now fails in CI instead of passing unnoticed.
- **CORS preflight (`OPTIONS`) is handled by the bundle** — the `/api/v{version}` route accepts `OPTIONS`, and `ApiController` answers `204 No Content` with `Access-Control-Allow-Methods`, `Access-Control-Allow-Headers` and `Access-Control-Max-Age: 86400` before any JSON-RPC parsing. An external CORS bundle or reverse proxy is no longer required.
- **`cors_allowed_headers`** (default `['Content-Type']`) — the list of headers reflected in the `Access-Control-Allow-Headers` of a preflight response.
- **CI matrix** — PHP 8.2/8.3/8.4/8.5 against Symfony 6.4/7.x/8.x, a job on the lowest permitted dependencies, a coverage gate (90% floor), weekly `composer audit` / `composer validate --strict`, and a non-blocking Symfony 9 canary.

### Changed (BC-breaking)
- **Content-Type is mandatory for requests with a body.** A POST/PUT/PATCH/DELETE without `Content-Type: application/json` is `-32600 Invalid Request`, even when the body is valid JSON. Form-encoded and multipart are rejected on the same grounds: form-encoded is a "simple request" under the CORS specification, and without this check a third-party HTML form could call RPC methods as the logged-in user with no preflight (CSRF). It also closes a bypass of `max_payload_bytes`, which used to inspect only the raw body — and PHP puts form-encoded content somewhere else.
- **Scalars from a query string are read as the declared type.** A GET request has no body; its payload comes from the query string, which carries no types by nature — PHP parses it into strings. So on the GET branch `int`, `float` and `bool` are read as the declared property type asks, at any depth: in nested DTOs, in collection elements and in constructor parameters. Only unambiguous representations are recognised (booleans use PHP's filter set: `1/true/on/yes` and `0/false/off/no`); everything else is rejected by the validator as before — `?params[id]=abc` is `-32602`. Strictness on the JSON branch is unchanged: JSON has types, and `"42"` where `42` belongs stays a client error.
- **Scalar parameters are no longer coerced.** `"42"` for an `int` field is `-32602 Invalid params`, not a silent cast. Any `TypeError` surfacing from client input — a DTO constructor, a setter, an adder, a nested DTO — now becomes `-32602` instead of `-32603` or an unhandled exception in the log.
- **A response serialises only what the class makes public.** A field reaches the JSON if it has a public getter (`getFoo()` / `isFoo()` / `foo()`) **or** the property itself is public. Private and protected properties with no public getter do not — and that was the defect: serialisation read properties straight off the object through Reflection, so a private field (a password, an internal token) went to the client. A private getter does not qualify either: nothing outside the class can call it. Public properties, including promoted constructor parameters, serialise as before — the line is visibility, not the presence of a getter. Fixed alongside: a field inherited from a parent class is no longer lost (`ReflectionClass::getProperties()` does not return a parent's private properties).
- **A cyclic object graph in a response is `-32603`, not a dead worker.** A bidirectional relation (order → user → their orders) used to segfault: no response, no log, and no chance for `ErrorSanitizer` to act, because a stack overflow is not an exception. **A nesting ceiling of 64 levels comes with it**, because a self-referencing array (`$a['self'] = &$a`) cannot be caught by identity: arrays in PHP are values, and `SplObjectStorage` does not tell them apart. An acyclic but deeper response is now rejected too — `-32603 Value nesting is too deep to serialise.` The ceiling is not configurable: 64 matches the default `max_json_depth`, so anything deeper could not have been accepted on the way in either.
- **`DateTimeInterface` serialises to ISO 8601.** Both in a response (`BaseResponse` had no special handling at all — `DateTime`/`DateTimeImmutable` fell apart into an empty array of internal properties) and in `JsonRpcRequest::toArray()` (which did not format dates either, returning the object as-is to fall apart during encoding).
- **DTO methods bind to properties by exact name**, not by substring. `getUserId()` can no longer accidentally satisfy both `userId` and `id`. Collection adders resolve from the property name through `Symfony\Component\String\Inflector\EnglishInflector` (`children` → `addChild`) instead of dropping the last letter (`children` → `childre`, silently adding nothing).
- **`allow_extra_fields` now applies identically at any depth.** The flag used to be checked once at the top level, while recursive hydration of nested DTOs rejected extra fields unconditionally.
- **A batch is detected by the shape of the container**, not by its first element. Any non-empty JSON array is a batch, even when some elements are invalid. Previously an invalid first element dropped the whole array back to single-request handling, and every valid call further along was lost without a trace.
- **`{"id": null}` is a full request** and receives a response with `"id": null`. A Notification is the *absence* of `id`, not a `null` value; `isset()` did not tell the two apart. A Notification now receives no response at all, including an error one — except when the request envelope is damaged badly enough that it cannot be established whether it was a Notification.
- **`params: null` is rejected** (`-32600`) rather than silently treated as "no parameters" — the same `isset()` trap as with `id`.
- **A role denial is `-32000` inside a JSON-RPC error object with HTTP 200**, not HTTP 403 with a bare string. Inside a batch that bare string used to break the structure of the element and lose its `id`.
- **Error codes outside the permitted JSON-RPC ranges are normalised to `-32603`.** An arbitrary `Throwable` with code `0` — or any other value outside the standard codes and the `-32000..-32099` range — no longer reaches the client as-is, not even with `expose_internal_errors: true`.
- **OpenAPI schema names no longer collide.** A response schema is `{methodName}Response`, and nested DTOs are named after the full class name (`\` → `.`). Previously the short class name (`Response`, `Request`) collected every identically-named DTO in the project into one schema, silently overwriting the previous one.
- **`cors_strict` is removed.** Behaviour is always what `cors_strict: true` gave in 4.x — the legacy comma-joined mode no longer exists. A leftover `cors_strict` key is now a container compilation error rather than a silently ignored value.
- **Dependency bounds in the manifest.** `composer.json` now declares upper bounds (`^6.4 || ^7.0 || ^8.0` for Symfony components, `8.2.* || 8.3.* || 8.4.* || 8.5.*` for PHP) instead of open-ended `>=`. The bounds state what CI verifies: ten PHP × Symfony combinations, excluding Symfony 8 below PHP 8.4, since Symfony 8 requires PHP >= 8.4.1. `doctrine/annotations` is dropped from require — nothing in `src/` or `config/` relied on it.
- **The container is no longer aliased into the application, and RPC methods are no longer public services.** `config/services.yaml` aliased `Symfony\Component\DependencyInjection\Container` onto `@service_container`, which let any service in a consuming application autowire the entire container through this bundle. That alias was also the reason every RPC method had to be registered as a public service. `RequestHandler` now receives a `ServiceLocator` assembled from the `ov.rpc.method` tag, and neither the alias nor the public flag is needed. **There is deliberately no replacement:** if one of your services type-hinted `Container`, inject the services you actually need. Nineteen classes in `src/DependencyInjection/` and `src/Swagger/` are marked `@internal`, and `MethodSpecCollection::getMethodNames()` is removed (it returned API version numbers, not method names).
- **Sensitive-data masking in logs is on by default.** `logging.masking.key_patterns` used to default to an empty list and `logging.max_body_length` to zero, so a single `logging.enabled: true` was enough to write complete request and response bodies, passwords included, and the operator was expected to work out the pattern list unaided. `key_patterns` now holds twenty-nine patterns and `max_body_length` is 8192. Applications that already had logging enabled will see `***` in place of values, and truncated bodies. A broken regular expression in `key_patterns` now fails container compilation instead of silently disabling masking for that pattern at runtime.
- **An exception from a PostProcessor no longer propagates.** The `finally` block in `processBatch()` is wrapped in `try/catch (Throwable)`: the failure is written to the logger as `JSON-RPC post-response stage failed` and does not affect the response. `finally` runs after the response has been formed — and, in batch mode, after every element — so an exception from there used to overwrite a finished result with an error and could abort processing of the remaining elements. **A PostProcessor that threw deliberately** (to fail an audit, to refuse delivery of a response) now has no effect; move that logic into the method itself or into a PreProcessor. See [docs/upgrade-5.0.md](./docs/upgrade-5.0.md) item 22.
- **`JsonRpcRequest::toArray()` serialises the same way a response does.** The method is public and the documentation recommends it for logging a request, yet it read properties straight off the object through Reflection — so a private field with no getter (a password, an internal token) went into the log. A field is now exported only if it has a public getter. Mutual references between request DTOs used to overflow the stack and kill the worker with no response and no log entry; a `JRPCException` with code `-32603` is thrown instead — **so the method can now throw.** A nested object with its own `toArray()` still decides its own shape; the exception is another `JsonRpcRequest`, which is walked by the shared mechanism, since otherwise the traversal would start over and a cycle would kill the process again.
- **`ResponseService` requires `ErrorSanitizer` explicitly.** The second constructor parameter is no longer optional: `new ResponseService($headersPreparer)` is now an `ArgumentCountError`. Previously a test harness could assemble a `ResponseService` without a sanitiser, and validate a configuration that never existed in production.
- **`HeadersPreparer` changed its third constructor parameter.** In place of the removed `bool $corsStrict` there is now `array $allowedHeaders` — the old positional call `new HeadersPreparer($list, $stack, true)` is a `TypeError` rather than an ignored value. With an empty origin whitelist, `prepareHeaders()` returns an empty array instead of `['Access-Control-Allow-Origin' => '']`: an empty header is no longer emitted at all.

- **The request-handling pipeline is marked `@internal`.** To the nineteen classes from `src/DependencyInjection/` and `src/Swagger/` are added `ApiController`, `RequestHandler`, `RequestRawDataHandler`, `ResponseService`, `HeadersPreparer`, `ErrorSanitizer`, `BatchStrategyFactory`, both batch strategies, `HandleBatchInterface` and the serialisation trait — thirty in all. None of them is constructed by an application; the container does that. The public contract remains the extension-point interfaces, `JsonRpcRequest`, `BaseRequest`, `BaseResponse`, `JRPCException`, the `#[JsonRPCAPI]` attribute and the configuration keys.
- **`'*'` alongside specific origins no longer compiles.** The wildcard was checked first and won, so `['https://app.example.com', '*']` answered `Access-Control-Allow-Origin: *` to everyone: the list read as a whitelist and behaved as its opposite, and nothing in the application's behaviour showed the difference. Either enumerate the origins or use `'*'` on its own.

### Fixed
- **`Vary: Origin` is sent on refusal too, not only on a match.** The presence of a CORS header depends on `Origin` in both directions, and a shared cache unaware of that could serve a header-less response to a client with an allowed origin — or one origin's header to another.
- **`Content-Type: application/json` with a NUL byte is no longer accepted.** `trim()`'s default character list includes `\0`, so a media type padded with a control byte passed the comparison. Only spaces and tabs are trimmed now.
- **An invalid `id` in a response is now `null` rather than reflected back.** The specification, section 5: "If there was an error in detecting the id in the Request Object … it MUST be Null". `BaseRequest` rejects an `id` of boolean, array or object type, but on the error path the raw value was taken from the decoded payload without that check — and the response reporting an invalid request was itself invalid. As a side effect this was a channel for reflecting arbitrary structures of up to `max_payload_bytes`. Three conformance tests for these cases existed but only checked `error.code` and never looked at `id`.
- **A missing object field is `-32602`, not `-32603`.** A field absent from the request was not hydrated, yet the validation stage still read it through its getter: a typed property with no value raises `Error` ("must not be accessed before initialization"), not `JRPCException`, and the sanitiser turned that into an internal server error. A client that forgot a field was told the server was broken. The defect depended on class-loading order — `class_exists()` is called here without autoloading — so it did not always appear.
- **An empty collection `{"items": []}` is accepted.** The adder branch was gated by a `!empty()` check, so an empty list fell through to the setter branch — where the type has already been rewritten to the *element* type of the collection, and an empty array was assembled into a single bare element. The client received `[items] - This value should be of type Item` and had no way to send an empty list at all. Other values did not change branch: `null` and `0` still go to the setter, and a string is still rejected with "must be an array".
- **A DTO getter resolves identically at both assembly stages.** Validator collection derived a single hard-coded name — `isX` for a boolean property, `getX` for everything else — and ran first, so it decided for everyone. A boolean property `$isActive` with the natural accessor `isActive()` broke container compilation by demanding a method named `isIsActive()`, while the bare accessor (`title()`), advertised by the other resolver, was unreachable. Both stages now use one candidate list — `getX`, `isX`, `x` — and the error message lists all three forms instead of one.
- **By-position parameters (`params` as an array, specification section 4.2) work again.** Hydration always understood the pseudo-field `params`, but the validation stage did not: it saw a list with keys `0..n`, reported that the field `params` was missing and that every element was extra, and rejected any by-position call with `-32602`. Every example from the specification and from the documentation was broken, including the canonical `subtract` with `[42,23]`. The defect went unnoticed because the conformance suite ran against a mock validator that returns an empty violation list for any input — the suite now runs the real one.
- **A request DTO constructor error no longer carries server paths or internal class names.** A `TypeError` from a constructor carries an absolute file path, a line number and an FQCN, and that reached the client under the default `expose_internal_errors: false`: the error was wrapped in a `JRPCException`, and `ErrorSanitizer` passes those through by definition. Only the argument position is now taken from the original message, and the client-facing text is assembled from the method specification in the same format the hydration path uses: `[id] - This value should be of type int`.
- Well-formed JSON that is not a Request object (`"42"`, a bare string, `true`, `null`) is now `-32600 Invalid Request` per section 5.1 of the spec, rather than `-32700 Parse error`. Invalid JSON remains `-32700`.
- The GET branch now applies `max_payload_bytes` and `max_json_depth` — both used to be bypassed by moving the payload into the query string. **An asymmetry:** on the GET branch `max_json_depth` is effectively capped by `max_input_nesting_level` from `php.ini` (default 64), because PHP parses the query string before the bundle is given control and truncates deeper structures itself. On the POST branch the limit is honoured in full.

### Known limitations
- An `id` above `PHP_INT_MAX` is not echoed back byte for byte: `json_decode()` turns it into a lossy `float`.

---

## [4.2] - 2026-05-19

### Added
- **Pluggable PSR-3 logger** — two new config keys, `logging.logger_service` and `logging.call_logger_service`, plus a bundle-scoped alias `ov_json_rpc_api.logger`. They allow replacing either the internal PSR-3 sink that `JsonRpcCallLogger` writes to (`logger_service` / alias `ov_json_rpc_api.logger`), or the whole `JsonRpcCallLoggerInterface` implementation (`call_logger_service` / alias `JsonRpcCallLoggerInterface`). An alias declared by hand in the project's `services.yaml` beats the config key and even the `logging.enabled: false` kill switch — Symfony DI merges the project's `services.yaml` into the container before the bundle extension is compiled. The `JsonRpcLogFormatterInterface` override point from 4.1 is unchanged. Details and the full precedence table — [docs/logging.md](./docs/logging.md).

---

## [4.1] - 2026-05-14

### Added
- **Request/response logging** — an optional subsystem, off by default. `ov_json_rpc_api.logging.enabled: true` turns on the default logger with the format `Request: [method] {body} context_id: <uuid>` and a matching `Response`. It supports masking sensitive fields by regular expressions over JSON key names (`logging.masking.key_patterns`), a custom formatter (`JsonRpcLogFormatterInterface`), a custom masker (`SensitiveDataMaskerInterface`) and a custom context-id generator (`ContextIdGeneratorInterface`). Details and examples — [docs/logging.md](./docs/logging.md).

---

## [4.0] - 2026-05-11

A security-hardening release. Migration details — [docs/upgrade-4.0.md](./docs/upgrade-4.0.md).

### Added
- **Error sanitisation** — a new service, `OV\JsonRPCAPIBundle\Core\Services\ErrorSanitizer`. Any `Throwable` other than `JRPCException` is replaced with a generic `Internal error.` (`-32603`); the full exception goes to `Psr\Log\LoggerInterface`. Controlled by the `expose_internal_errors` config key (default `false`).
- **DoS limits** with safe defaults:
  - `max_payload_bytes` (1 MiB) — the size of the raw HTTP request body.
  - `max_json_depth` (64) — JSON nesting depth.
  - `max_batch_size` (50) — the number of requests in one batch.
  - `max_dto_depth` (10) — recursion depth when hydrating nested DTOs.
  - `max_array_param_size` (1000) — the number of elements in an array parameter passed through `addX()`.
- **CORS origin matching** — `HeadersPreparer` now takes a `RequestStack`, reads the `Origin` header and matches it against the whitelist. `Vary: Origin` is added. Config key `cors_strict` (default `true`).
- **Security regression suite** — `tests/Security/`: `PayloadLimitTest`, `BatchSizeLimitTest`, `DtoHydrationLimitsTest`, `ArrayParamLimitTest`, `ErrorSanitizationTest`, `CorsMultiOriginTest`, `SwaggerGenerateSecurityTest`.
- **New documentation:**
  - [docs/security_hardening.md](./docs/security_hardening.md) — every new config key plus tuning.
  - [docs/upgrade-4.0.md](./docs/upgrade-4.0.md) — migration from 3.x.
  - [docs/cors.md](./docs/cors.md) — CORS behaviour.
  - [docs/batch.md](./docs/batch.md) — batch semantics and limits.
  - [docs/testing.md](./docs/testing.md) — a guide to writing tests for RPC methods.
- **Coverage tooling in `phpunit.xml.dist`** — a `<source>` block for PHPUnit 10+ coverage reports.
- **Direct unit tests** for previously uncovered classes: `OVJsonRPCAPIExtension`, `MethodSpec\RequestMetadata`, `MethodSpec\SwaggerMetadata`, `PartialUpdateRequest`, plus wider coverage of HTTP method enforcement and `INTERNAL_ERROR` scenarios.
- **English README** — a Testing section was added, bringing it to parity with the Russian one.

### Changed (BC-breaking)
- **`strict_notifications` now defaults to `true`** — conformance with the JSON-RPC 2.0 spec. Notifications (requests without `id`) **never** receive a response. For legacy behaviour: `strict_notifications: false`.
- **`expose_internal_errors` defaults to `false`** — production-safe. Raw exception messages no longer leak to the client.
- **`cors_strict` defaults to `true`** — multi-origin now matches the request `Origin`; for an origin outside the whitelist no CORS header is sent.
- **DoS limits are on by default** — large payloads, batches, deeply nested DTOs and huge arrays are now rejected with `INVALID_REQUEST` / `INVALID_PARAMS`. Tunable through configuration.
- **Non-public setters in request DTOs are rejected** — `RequestHandler::prepareParametersFromClass()` now requires `ReflectionMethod::isPublic()`. Private and protected setters are no longer called through dynamic dispatch.
- **`SwaggerGenerate::__construct($name)` is now explicitly `?string`** — a fix for the PHP 8.4 implicit-nullable deprecation.
- **`HeadersPreparer::__construct(array, ?RequestStack, bool)`** — two optional arguments added. An existing `new HeadersPreparer(['*'])` keeps working.
- **`ResponseService::__construct(HeadersPreparer, ?ErrorSanitizer)`** — an optional second argument added.
- **A parse-error `message` now carries the reason** — `"Parse error. Additional info: Syntax error"` instead of `"Parse error."`. Match on `code === -32700`, not on the text.
- **`tests/Controller/AbstractTest` → `AbstractControllerTestCase`** — renamed for the PHPUnit 10+ convention. If you extended it, update the class name.

### Fixed
- **CORS multi-origin bug** — a list of several origins is no longer joined with `, ` (which violated the CORS specification). Exactly one origin is returned, the one matching the request `Origin` header.
- **PHP 8.4 deprecation in `SwaggerGenerate`** — the `$name` parameter became explicitly `?string`.
- **`SwaggerGenerate` path containment** — `realpath()` validation of the target directory; on an invalid path the command returns `FAILURE` with a clear message.

### Security
- HIGH: DoS through unbounded payload size — closed by `max_payload_bytes`.
- HIGH: DoS through unbounded batches — closed by `max_batch_size`.
- HIGH: DoS through unbounded DTO nesting recursion — closed by `max_dto_depth`.
- HIGH: DoS through unbounded array-parameter expansion — closed by `max_array_param_size`.
- HIGH: information disclosure through `Throwable::getMessage()` — closed by `ErrorSanitizer`.
- MEDIUM: invalid CORS header with multiple origins — fixed by origin matching.
- LOW: hardening of DTO hydration — private and protected setters are not called.

---

## [3.9] - 2026-05-07

### Added
- **JSON Merge Patch (RFC 7396) support at the request-DTO level** — an opt-in contract that lets the service layer tell "the field was not sent in the payload" from "the field was sent as `null`" (= clear it).
  - **Interface `OV\JsonRPCAPIBundle\Core\Request\PartialRequestInterface`** — a contract with `markProvided(string)`, `wasProvided(string): bool` and `getProvidedFields(): array`.
  - **Trait `OV\JsonRPCAPIBundle\Core\Request\TracksProvidedFieldsTrait`** — the default implementation of that contract.
  - **Base class `OV\JsonRPCAPIBundle\Core\Request\PartialUpdateRequest`** — `extends JsonRpcRequest implements PartialRequestInterface; use TracksProvidedFieldsTrait;`. A convenient shortcut for PATCH scenarios.
  - **`RequestHandler::hydrateRequest()`** — after a property is set successfully, and if the DTO implements `PartialRequestInterface`, the framework calls `markProvided($name)` ONLY when the key was actually present in the raw payload. It is not called for the `defaultValue` branch or for the synthetic `params` field.
  - **`RequestHandler::prepareParametersFromClass()`** — symmetric support for recursive nested DTOs (the RFC 7396 object merge).
- **Test `TracksProvidedFieldsTraitTest`** — a unit test of the trait's contract.
- **Test `PartialRequestHydrationTest`** — integration tests of hydration:
  - key present with a value → `wasProvided` true;
  - key present with `null` → `wasProvided` true (the field is cleared);
  - key absent → `wasProvided` false;
  - `defaultValue` applied → `wasProvided` false;
  - a DTO without the interface is untouched (a BC check);
  - a nested DTO with the interface tracks its fields correctly.

### Backward compatibility
- Fully preserved. DTOs that do not implement `PartialRequestInterface` behave exactly as in 3.8 — the `instanceof` check short-circuits, at zero cost.
- No new required parameters in any public API. No config flag is needed — the feature is opted into through the interface.

---

## [3.8] - 2026-04-13

### Added
- **An `allowExtraFields` setting to switch off validation of extra fields** — it permits parameters that the request class does not describe, without a `-32602` error.
  - **Globally** — `allow_extra_fields: true` in the `ov_json_rpc_api.yaml` configuration disables the check for every method.
  - **Per method, through the attribute** — `allowExtraFields: true` in `#[JsonRPCAPI]` disables the check for that one method. It only applies while the global setting is `false` (the default); the global configuration always wins.
- **Test `DenyExtraFieldsDefaultTest`** — verifies that extra fields are rejected by default.
- **Test `AllowExtraFieldsGlobalTest`** — verifies the global `allow_extra_fields: true`.
- **Test `AllowExtraFieldsAttributeTest`** — verifies `allowExtraFields: true` through the method attribute.
- **Test `GlobalOverridesAttributeTest`** — verifies that a global `allow_extra_fields: true` beats a local `allowExtraFields: false`.

---

## [3.7] - 2026-04-09

### Fixed
- **The request's root `id` and `params.id` no longer bleed into each other** — a business parameter named `id` inside `params` could be replaced by the root JSON-RPC `id`, and the other way around. The two are now fully isolated: the root `id` is used only to correlate request and response, and `params.id` only for the request DTO's business logic.
  - `instantiateRequest()` — removed the special branch that passed the root `id` into the request DTO's constructor in place of `params.id`.
  - `hydrateRequest()` — removed the `if ($name === 'id')` block that replaced the value from `params` with a fallback to the root `id`.
  - `processValidatorsForRequestInstance()` — removed the merge of the root `id` into the validation data, which could overwrite `params.id`.

### Added
- **Test `ParamsIdAndRootIdDoNotConflictTest`** — verifies that when the root `id` and `params.id` differ in a single request, the response carries the root `id` while the business logic receives `params.id`.
- **Test `BatchParamsIdAndRootIdDoNotConflictTest`** — the same check for batch requests with several elements.

---

## [3.5] - 2026-04-09

### Fixed
- **Version detection from nested namespaces** — a method in `App\RPC\V1\SubDirectory\` is now correctly resolved to version 1. The regular expression used to require `V{N}` strictly at the end of the namespace, which failed for any directory nesting.

### Changed
- **Test fixtures moved from `src/RPC/` to `tests/Fixtures/RPC/`** — the bundle's production code no longer contains test controllers. The fixture namespace is unchanged (`OV\JsonRPCAPIBundle\RPC\...`); they are loaded through `autoload-dev`.
- **The code was brought to the PER-2 standard** — empty class and interface bodies, constructors, grouped `use` statements, constants, missing braces on `if`, and spacing around operators.
- **`phpunit.xml.dist` created** — a standard PHPUnit configuration that excludes `tests/Fixtures` from scanning.

### Refactoring
- **`SwaggerSchemaBuilder`** — Swagger schema generation moved out of the `SwaggerGenerate` CLI command into a dedicated service, `OV\JsonRPCAPIBundle\Swagger\SwaggerSchemaBuilder`. The command became a thin adapter.
- **Value objects `RequestMetadata` and `SwaggerMetadata`** — `MethodSpec`'s parameters grouped into two value objects. The `MethodSpec` constructor takes 8 parameters instead of 19. Every old getter is preserved through delegation (backward compatibility).
- **`CompilerPass::process()` split into methods** — `extractAttributeMetadata()`, `resolveVersion()`, `analyzeRequestClass()`, `detectPlainResponse()`, `detectProcessors()`.
- **`RequestHandler::processRequestClass()` split** into `instantiateRequest()` and `hydrateRequest()`.
- **`Schema::addPropertyWithRequired()`** — removed 16 duplicates of the `addProperty` + `if ($required) addRequired` pattern.
- **`BatchStrategyFactory`** — reduced to a ternary, with `self::` instead of the full class name.
- **Inverted variable names fixed** — `$setterAndPropertyTypesAreEqual` (under a `!==`) renamed to `$setterTypeMismatch`.
- **`MethodSpecCollection`** — `$version` changed from `string` to `int` for type safety.

### Performance
- **Response serialisation is 3× faster** — the Symfony Serializer (`ObjectNormalizer`) replaced by `json_encode` over a `toArray()` method on `BaseResponse` and `ErrorResponse`.
- **Batch requests are 1.8× faster** — double serialisation removed from `MultiBatchStrategy` (previously: serialize → json_decode → json_encode; now: concatenation of ready JSON strings).
- **`HeadersPreparer`** — the result is computed once, in the constructor.
- **`checkRoles()`** — a `break` added after the first permitted role.

---

## [3.4] - Previous stable release

The baseline version of the bundle.
