[English](multipart.md) · [Русский](multipart.ru.md)

# File uploads (`multipart/form-data`)

JSON has no representation for a file. A method can opt into receiving one as a part of a `multipart/form-data` request, while everything else about the call — the method name, the scalar parameters, the `id`, the response — stays an ordinary JSON-RPC request object.

The feature is **off by default** and has to be switched on in two places: once for the application, once for the method. Both switches are described below, and the reason there are two of them is [security](#security-read-this-before-switching-it-on), not ceremony.

## The request shape

A multipart request carries one text part named `jsonrpc`, holding the complete JSON-RPC request object serialised as a string, plus one part per file. **Every part that is not `jsonrpc` is a file, and its part name is the parameter name.**

```bash
curl -X POST http://localhost/api/v1 \
  -F 'jsonrpc={"jsonrpc":"2.0","method":"uploadFile","params":{"title":"Quarterly report"},"id":"1"}' \
  -F 'file=@./report.pdf'
```

```json
{
    "jsonrpc": "2.0",
    "result": {"originalName": "report.pdf", "title": "Quarterly report", "size": 51234},
    "id": "1"
}
```

Scalar parameters stay inside the `jsonrpc` field rather than becoming form fields of their own. A form field is a string, so `"42"` and `42` would become indistinguishable again — the untyped-transport problem the bundle tolerates only for GET, where a query string leaves no alternative. Keeping them in the JSON envelope also removes the question of which value wins when the same name appears as a form field and inside `params`.

## Switching it on

### 1. The application

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    multipart:
        enabled: true         # default: false
        max_file_bytes: '10Mi'    # default: '10Mi', per file
        max_files: 10             # default: 10, per request
```

With `enabled: false` — the default — nothing changes at all: a `multipart/form-data` request is refused exactly as it was before this feature existed.

`max_file_bytes` is handed to `Assert\File::maxSize`, so it takes Symfony's own size notation — `'512k'`, `'10M'`, `'2Mi'`, `'1G'` — as well as a bare byte count. A spelling Symfony cannot parse fails the container build rather than the first request that carries a file.

`max_payload_bytes` applies to the `jsonrpc` field, the same way it applies to a JSON body. Files are bounded separately, because the envelope and a file part are different kinds of input and raising the limit for one should not raise it for the other.

PHP's own `upload_max_filesize`, `post_max_size` and `max_file_uploads` still apply, and they apply *first* — they are the settings that actually stop a large body from being read. `max_file_bytes` is your application's policy on top of them, expressed where the rest of your API configuration lives; `UploadedFile::getMaxFilesize()` reports what PHP will allow through.

### 2. The method

```php
#[JsonRPCAPI(methodName: 'uploadFile', type: 'POST', version: 1, acceptsMultipart: true)]
final class UploadFileMethod
{
    public function call(UploadFileRequest $request): UploadFileResponse
    {
        $file = $request->getFile();   // Symfony\Component\HttpFoundation\File\UploadedFile

        return new UploadFileResponse($file->getClientOriginalName());
    }
}
```

A method that does not declare `acceptsMultipart: true` answers a multipart request with `-32600 Invalid Request`, even when the application switch is on. The content type has to be checked before any method is known, so the global switch alone cannot say anything about a particular method — and turning the transport on for the application must not silently open every method already written to a transport none of them expected.

`acceptsMultipart: true` also tells the OpenAPI generator to describe this method with a `multipart/form-data` request body.

### 3. The request DTO

Declare the parameter with the `UploadedFile` type, and hydration does the rest:

```php
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UploadFileRequest
{
    private UploadedFile $file;
    private string $title = '';

    public function getFile(): UploadedFile { return $this->file; }
    public function setFile(UploadedFile $file): void { $this->file = $file; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): void { $this->title = $title; }
}
```

Validation needs nothing new **from you or from this bundle**: a declared `UploadedFile` compiles to `Assert\Type(UploadedFile::class)` followed by [`Assert\File`](https://symfony.com/doc/current/reference/constraints/File.html), through the same compiler that produces `Assert\Type('int')` for an `int` field.

`Assert\File` is what enforces `max_file_bytes`, and it brings the rest of Symfony's upload handling with it — so a request that reaches `call()` has a file that really arrived:

| The caller sends | The answer |
|---|---|
| something that is not a file | `-32602` — `Assert\Type` answers first, so a JSON string path such as `/etc/passwd` is *not* accepted as a file |
| a file above `max_file_bytes` | `-32602 … The file is too large (25 bytes). Allowed maximum size is 4 bytes.` |
| an upload that failed (`upload_max_filesize`, partial transfer, no temp dir, …) | `-32602` with Symfony's message for that PHP upload error code |
| an empty file | `-32602 … An empty file is not allowed.` |

That last group is the reason the constraint is there rather than a size comparison of our own. PHP reports a failed upload by handing over an `UploadedFile` whose `isValid()` is false and whose temporary file does not exist; without `Assert\File` that object reaches your method, which then reads a file that is not there.

Rules specific to one method — MIME types, extensions, image dimensions, a tighter size than the global one — belong on the method through the injected validator, the same as any other content-level check:

```php
public function call(UploadFileRequest $request): UploadFileResponse
{
    $violations = $this->validator->validate($request->getFile(), new Assert\Image(
        maxSize: '2Mi',
        mimeTypes: ['image/jpeg', 'image/png'],
        maxWidth: 4096,
    ));
    // ...
}
```

> `mimeTypes` and `extensions` need `symfony/mime` installed — the constraint guesses the real type
> from the file's content rather than trusting what the client claimed. It is not a dependency of
> this bundle, because nothing here needs it; add it to your application if you use those options.

## Testing a method that takes a file

A functional test has to set the content type by hand:

```php
$client->request(
    'POST',
    '/api/v1',
    ['jsonrpc' => json_encode(['jsonrpc' => '2.0', 'method' => 'uploadFile', 'params' => ['title' => 'x'], 'id' => 1])],
    ['file' => new UploadedFile($path, 'report.pdf', 'application/pdf', null, true)],
    ['CONTENT_TYPE' => 'multipart/form-data; boundary=----boundary'],
);
```

BrowserKit builds its requests through `Request::create()`, which labels a POST
`application/x-www-form-urlencoded` unless told otherwise. The transport is read from that header,
so without the fifth argument the request arrives looking like an ordinary form post with an empty
body — and comes back `-32600` for a reason that has nothing to do with the test.

## What is refused, and why

| Request | Answer |
|---|---|
| `multipart/form-data` with `multipart.enabled: false` | `-32600` — as before this feature existed |
| A method without `acceptsMultipart: true` | `-32600 … Method does not accept multipart/form-data.` |
| No `jsonrpc` part, or one that is not a single string | `-32600 … A multipart request must carry the JSON-RPC request object in the "jsonrpc" field.` |
| A batch (a JSON array) inside `jsonrpc` | `-32600 … Batch requests are not supported over multipart/form-data.` |
| A part named like a key already in `params` | `-32600 … File part "x" collides with a parameter of the same name.` |
| Files alongside by-position `params` | `-32600 … File parameters require by-name params.` |
| A nested part (`photos[]`, `photo[thumb]`) | `-32600 … File part "photos" must be a single file at the top level.` |
| More parts than `max_files` | `-32600 … File count 3 exceeds limit 2.` |
| `multipart/form-data` on PUT/PATCH/DELETE | `-32600 … multipart/form-data is supported for POST requests only.` |

A file above `max_file_bytes`, or one whose upload failed, is **not** in this table: those are `-32602 Invalid params`, because they are facts about the value of a parameter rather than the shape of the request — and the violation names the field. See the table in the previous section.

Two of the rows above are worth a sentence each.

**Batch stays JSON-only.** A file part carries one name, and there is no way to say which member of a batch it belongs to. A batch that needs files is several requests.

**Files live at the top level of `params` only.** A file cannot be a property of a nested DTO, and a repeated part name cannot become an array of files. Both are refusals rather than silent omissions, and both are limitations of this release rather than decisions about the shape of the protocol.

## Logging

The call logger records request parameters, so an uploaded file reaches it inside `params`. It is written as its metadata and never as its content:

```
Request: [uploadFile] {"jsonrpc":"2.0","method":"uploadFile","params":{"title":"Quarterly report","file":{"originalName":"report.pdf","size":51234,"mimeType":"application/pdf"}},"id":"1"} context_id: ...
```

`mimeType` is the one the client claimed, not one the server derived — treat it as caller input. The server-side temporary path never appears. Masking applies first: a part named after a credential (`signature`, `token`) is replaced by the placeholder instead of being described, exactly like any other field of that name. See [logging.md](./logging.md).

## Security: read this before switching it on

**`multipart/form-data` is a CORS "simple request".** A cross-origin HTML form can POST it with the user's cookies and no preflight. The mandatory `Content-Type: application/json` introduced in 5.0 is what closes that CSRF vector ([security_hardening.md](./security_hardening.md)), and `multipart.enabled: true` deliberately opens a hole in it — for the methods that declare `acceptsMultipart: true`, and only for those.

This is the whole reason the switch is off by default and the method has to opt in as well. Before turning it on, make sure at least one of the following holds for the methods concerned:

- **Authentication does not travel in cookies.** A `Authorization` / `X-AUTH-TOKEN` header is not CORS-safelisted, so a cross-origin form cannot send one, and the request arrives unauthenticated. This is the simplest answer, and it is what a JSON-RPC API usually does already.
- **Session cookies are `SameSite=Lax` or `Strict`.** Lax is the Symfony default (`framework.session.cookie_samesite: lax`) and it stops a cross-site form POST from carrying the session.
- **The method verifies a CSRF token** it reads from `params` — a value a cross-origin page cannot know.

Keep `acceptsMultipart: true` on the methods that genuinely receive files. Every method carrying it is a method a third-party page can attempt to POST to; every method without it is refused before it runs.

## Alternatives worth knowing

**Base64 inside JSON** works today with no configuration at all, and it is the right answer for small payloads — an icon, a signature, a thumbnail. Declare a `string` parameter, decode it in the method. It costs a third more bytes on the wire and holds the whole payload in memory, which is what makes it wrong for large files.

**A two-step upload** — a separate upload endpoint returns a file id, and the RPC method references that id — remains the right pattern for very large files, resumable uploads and files shared across several calls. It needs storage and a lifetime policy for the interim file, which is infrastructure the bundle should not own.
