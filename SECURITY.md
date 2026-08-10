[English](SECURITY.md) · [Русский](SECURITY.ru.md)

# Security Policy

## Supported versions

| Version | Support |
|---|---|
| 5.0.x | ✅ |
| 4.2.x | ✅ security fixes only, no new functionality |
| ≤ 4.1, ≤ 3.x | ❌ |

Majors before 4.0 receive no security patches. 4.0 was itself a
security-hardening release that closed the problems known at the time (see
[docs/upgrade-4.0.md](./docs/upgrade-4.0.md) and
[docs/upgrade-5.0.md](./docs/upgrade-5.0.md)). Upgrading to the current
major is the most reliable way to have the fixes.

## Reporting a vulnerability

**Do not open a public GitHub issue.** Use either:

- a [private security advisory](https://github.com/OtezVikentiy/symfony-jsonrpc-api-bundle/security/advisories/new)
  on this repository — private vulnerability reporting is enabled; or
- email **otezvikentiy@gmail.com** with a subject of the form
  `[SECURITY] <short description>`.

Please include:

- The bundle version, and Symfony/PHP versions where relevant.
- The configuration that reproduces the problem — in particular the values of
  `strict_notifications`, `expose_internal_errors`, `allow_extra_fields`,
  `access_control_allow_origin_list` and `cors_allowed_headers`. Anything you
  do not mention is assumed to be at its default.
- Steps to reproduce, ideally a minimal `curl` request with expected versus
  actual output.
- The impact as you see it: data disclosure, denial of service, authorisation
  bypass, and so on.

You will get a reply within five working days. If the report is confirmed, the
patch and the disclosure timeline are agreed with the reporter; the CHANGELOG
entry carries no exploitation detail until the patch has reached the large
majority of users.

## What counts as a vulnerability in the bundle, and what does not

**In scope:**

- Bypassing a limit (`max_payload_bytes`, `max_json_depth`, `max_batch_size`,
  `max_dto_depth`, `max_array_param_size`) at its default or documented value.
- Disclosure of data that the response contract does not describe — for
  example a field with no getter reaching the JSON anyway; see
  [docs/upgrade-5.0.md](./docs/upgrade-5.0.md) item 3.
- Bypassing the role check (`RequestHandler::checkRoles()`), the CORS
  whitelist, or the Content-Type check.
- Internal information leaking through a response — a stack trace, a file
  path, database connection details — while `expose_internal_errors: false`
  (the default).
- A denial-of-service vector that none of the existing limits covers.

**Out of scope** — not a vulnerability in the bundle as such:

- Behaviour under `expose_internal_errors: true`. That is a deliberate
  development mode, documented as unsafe for production; see
  [docs/security_hardening.md](./docs/security_hardening.md).
- The absence of `Access-Control-Allow-Credentials`. The bundle deliberately
  does not emit that header automatically; see [docs/cors.md](./docs/cors.md).
- The absence of built-in rate limiting. The bundle expects that at the
  reverse-proxy or middleware layer.
- Vulnerabilities in transitive dependencies (`symfony/*`). Report those to
  [Symfony Security Advisories](https://symfony.com/security/advisories). We
  track them through a weekly `composer audit` in CI, but patching Symfony is
  not ours to do.
- Problems in code written by the consumer of the bundle — a request or
  response DTO exposing a sensitive field, a public setter where a private one
  belongs. That is the integrator's responsibility; see
  [docs/upgrade-5.0.md](./docs/upgrade-5.0.md) for how response
  serialisation works.

## What is already in place

Briefly, for context before you report — the problem may already be closed:

- **Error sanitisation** (since 4.0) — any exception other than
  `JRPCException` reaches the client as a generic `Internal error.`
  (`-32603`); the full text goes only to the log. See
  [docs/errors.md](./docs/errors.md).
- **DoS limits** (since 4.0) — payload size, JSON nesting depth, batch size,
  DTO recursion depth, array parameter size, all with safe defaults. See
  [docs/security_hardening.md](./docs/security_hardening.md).
- **CORS origin matching and preflight** (4.0, extended in 5.0) — an exact
  match of `Origin` against the whitelist instead of joining the list; the
  bundle answers `OPTIONS` itself. See [docs/cors.md](./docs/cors.md).
- **Content-Type enforcement** (since 5.0) — form-encoded and multipart
  requests with a body are rejected, closing the CSRF vector that "simple
  requests" opened for methods with a body. **This does not apply to methods
  declared `#[JsonRPCAPI(type: 'GET')]`** — a GET request has no body and its
  payload comes from the query string. Such methods must be idempotent and
  free of side effects; declare anything that changes state as
  POST/PUT/PATCH/DELETE. See [docs/upgrade-5.0.md](./docs/upgrade-5.0.md)
  item 1 and [docs/security_hardening.md](./docs/security_hardening.md).
- **Strict parameter typing** (since 5.0) — scalars are not coerced, and
  non-public setters are not called.
- **Response serialisation limited to the public surface** (since 5.0) — a
  private field with no getter cannot leak into a response through bare
  Reflection.
- **Role denial returns a well-formed JSON-RPC object** (since 5.0) rather
  than HTTP 403 with a bare string that broke the structure of a batch
  response.

## Known trade-offs

Deliberate decisions rather than oversights. Listed so that you do not have to
discover them yourself.

- **Method existence is distinguishable before authorisation.** An unknown
  method answers `-32601 Method not found.`, while an existing one forbidden
  by role answers `-32000 Access denied.` The difference lets an
  unauthenticated client work out which methods you have. Collapsing both into
  one code would take away a legitimate client's ability to tell a misspelled
  method name from insufficient permissions. If the shape of your API is itself
  a secret, hide it at the network or reverse-proxy layer, not through error
  codes.
- **`max_payload_bytes` bounds processing, not buffering.** PHP reads the
  whole request body into memory before the bundle is given control, so the
  real memory ceiling is set by `post_max_size` in `php.ini` and by the web
  server's own limit. The bundle's key is a final check before decoding, not a
  defence against memory exhaustion; the complete picture is in
  [docs/security_hardening.md](./docs/security_hardening.md).
- **Log masking works on key names.** A secret in a field whose name does not
  match a pattern — and any parameter passed by position, since those have no
  names at all — reaches the log as it is. See
  [docs/logging.md](./docs/logging.md).

The full history is in [CHANGELOG.md](./CHANGELOG.md).
