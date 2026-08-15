[English](multipart.md) · [Русский](multipart.ru.md)

# Загрузка файлов (`multipart/form-data`)

В JSON нет представления для файла. Метод может явно согласиться принимать файл частью `multipart/form-data`-запроса — при этом всё остальное в вызове (имя метода, скалярные параметры, `id`, ответ) остаётся обычным JSON-RPC request object.

По умолчанию функциональность **выключена**, и включается она в двух местах: один раз для приложения, один раз для метода. Оба переключателя описаны ниже, и причина, по которой их два, — [безопасность](#безопасность-прочитайте-до-включения), а не формальность.

## Форма запроса

Multipart-запрос несёт одну текстовую часть с именем `jsonrpc` — в ней полный JSON-RPC request object, сериализованный в строку, — плюс по одной части на файл. **Любая часть, кроме `jsonrpc`, — это файл, а имя части — имя параметра.**

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

Скалярные параметры остаются внутри поля `jsonrpc`, а не превращаются в отдельные поля формы. Поле формы — это строка, поэтому `"42"` и `42` снова стали бы неразличимы: та самая проблема нетипизированного транспорта, которую бандл терпит только для GET, где query string не оставляет альтернатив. Кроме того, так не возникает вопроса, чьё значение важнее, когда одно и то же имя есть и в поле формы, и в `params`.

## Как включить

### 1. Приложение

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    multipart:
        enabled: true             # по умолчанию: false
        max_file_bytes: '10Mi'    # по умолчанию: '10Mi', на файл
        max_files: 10             # по умолчанию: 10, на запрос
```

При `enabled: false` — а это значение по умолчанию — не меняется ничего: `multipart/form-data`-запрос отклоняется ровно так же, как до появления этой функциональности.

`max_file_bytes` передаётся в `Assert\File::maxSize`, поэтому принимает привычную для Symfony запись размера — `'512k'`, `'10M'`, `'2Mi'`, `'1G'` — а также просто число байт. Запись, которую Symfony разобрать не может, роняет сборку контейнера, а не первый запрос с файлом.

`max_payload_bytes` применяется к полю `jsonrpc` — так же, как к JSON-телу. Файлы ограничиваются отдельно: конверт и файловая часть — разные виды входных данных, и поднятие лимита для одного не должно поднимать его для другого.

Настройки самого PHP — `upload_max_filesize`, `post_max_size`, `max_file_uploads` — продолжают действовать, и действуют *первыми*: именно они не дают прочитать большое тело. `max_file_bytes` — это политика вашего приложения поверх них, записанная там же, где остальная конфигурация API; `UploadedFile::getMaxFilesize()` показывает, что пропустит PHP.

### 2. Метод

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

Метод, не объявивший `acceptsMultipart: true`, отвечает на multipart-запрос `-32600 Invalid Request` — даже когда глобальный переключатель включён. Content-Type проверяется до того, как метод определён, поэтому глобальный переключатель сам по себе ничего не может сказать о конкретном методе; и включение транспорта для приложения не должно молча открывать этот транспорт всем уже написанным методам, которые его не ждали.

`acceptsMultipart: true` также говорит генератору OpenAPI описать этот метод с телом запроса `multipart/form-data`.

### 3. Request-DTO

Объявите параметр типом `UploadedFile` — остальное сделает гидрация:

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

Валидации не нужно ничего нового **ни от вас, ни от бандла**: объявленный `UploadedFile` компилируется в `Assert\Type(UploadedFile::class)`, а следом [`Assert\File`](https://symfony.com/doc/current/reference/constraints/File.html) — тем же компилятором, который даёт `Assert\Type('int')` для `int`-поля.

Именно `Assert\File` применяет `max_file_bytes` и приносит с собой остальную обработку загрузок из Symfony, так что до `call()` доходит файл, который действительно приехал:

| Клиент прислал | Ответ |
|---|---|
| не файл | `-32602` — первым отвечает `Assert\Type`, поэтому строка-путь вроде `/etc/passwd` в JSON файлом не считается |
| файл больше `max_file_bytes` | `-32602 … The file is too large (25 bytes). Allowed maximum size is 4 bytes.` |
| неудавшуюся загрузку (`upload_max_filesize`, обрыв, нет временной папки, …) | `-32602` с сообщением Symfony для соответствующего кода ошибки загрузки PHP |
| пустой файл | `-32602 … An empty file is not allowed.` |

Последняя группа — и есть причина держать здесь constraint, а не собственное сравнение размеров. О неудавшейся загрузке PHP сообщает, отдавая `UploadedFile`, у которого `isValid()` равен false, а временного файла не существует; без `Assert\File` этот объект доезжает до вашего метода, и метод читает файл, которого нет.

Правила, специфичные для одного метода — MIME-типы, расширения, размеры изображения, более строгий лимит, чем глобальный, — живут в методе через внедрённый валидатор, как и любая другая проверка содержимого:

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

> Для `mimeTypes` и `extensions` нужен установленный `symfony/mime` — constraint определяет
> настоящий тип по содержимому файла, а не верит тому, что заявил клиент. Зависимостью бандла он не
> является, потому что здесь он не нужен; добавьте его в приложение, если пользуетесь этими опциями.

## Как тестировать метод, принимающий файл

Функциональный тест обязан выставить content type вручную:

```php
$client->request(
    'POST',
    '/api/v1',
    ['jsonrpc' => json_encode(['jsonrpc' => '2.0', 'method' => 'uploadFile', 'params' => ['title' => 'x'], 'id' => 1])],
    ['file' => new UploadedFile($path, 'report.pdf', 'application/pdf', null, true)],
    ['CONTENT_TYPE' => 'multipart/form-data; boundary=----boundary'],
);
```

BrowserKit собирает запросы через `Request::create()`, а тот помечает POST как
`application/x-www-form-urlencoded`, если не сказано иное. Транспорт читается именно из этого
заголовка, поэтому без пятого аргумента запрос приезжает обычным form post с пустым телом — и
возвращает `-32600` по причине, никак не связанной с самим тестом.

## Что отклоняется и почему

| Запрос | Ответ |
|---|---|
| `multipart/form-data` при `multipart.enabled: false` | `-32600` — как и до появления функциональности |
| Метод без `acceptsMultipart: true` | `-32600 … Method does not accept multipart/form-data.` |
| Нет части `jsonrpc` либо она не одна строка | `-32600 … A multipart request must carry the JSON-RPC request object in the "jsonrpc" field.` |
| Batch (JSON-массив) внутри `jsonrpc` | `-32600 … Batch requests are not supported over multipart/form-data.` |
| Имя части совпадает с ключом в `params` | `-32600 … File part "x" collides with a parameter of the same name.` |
| Файлы вместе с позиционными `params` | `-32600 … File parameters require by-name params.` |
| Вложенная часть (`photos[]`, `photo[thumb]`) | `-32600 … File part "photos" must be a single file at the top level.` |
| Частей больше `max_files` | `-32600 … File count 3 exceeds limit 2.` |
| `multipart/form-data` на PUT/PATCH/DELETE | `-32600 … multipart/form-data is supported for POST requests only.` |

Файла больше `max_file_bytes` и неудавшейся загрузки в этой таблице **нет**: это `-32602 Invalid params`, потому что речь о значении параметра, а не о форме запроса, — и violation называет поле. См. таблицу в предыдущем разделе.

Две строки выше заслуживают пояснения.

**Batch остаётся только JSON.** У файловой части одно имя, и сказать, какому элементу батча она принадлежит, нечем. Батч, которому нужны файлы, — это несколько запросов.

**Файлы живут только на верхнем уровне `params`.** Файл не может быть свойством вложенного DTO, а повторяющееся имя части не станет массивом файлов. И то и другое — явный отказ, а не молчаливое игнорирование, и то и другое — ограничение этого релиза, а не решение о форме протокола.

## Логирование

Логгер вызовов пишет параметры запроса, поэтому загруженный файл попадает в него внутри `params`. Он записывается своими метаданными и никогда — содержимым:

```
Request: [uploadFile] {"jsonrpc":"2.0","method":"uploadFile","params":{"title":"Quarterly report","file":{"originalName":"report.pdf","size":51234,"mimeType":"application/pdf"}},"id":"1"} context_id: ...
```

`mimeType` — тот, который заявил клиент, а не выведенный сервером: относитесь к нему как к входным данным вызывающего. Временный путь на сервере в лог не попадает. Маскирование применяется раньше: часть с именем как у секрета (`signature`, `token`) заменяется плейсхолдером, а не описывается, — ровно как любое другое поле с таким именем. См. [logging.ru.md](./logging.ru.md).

## Безопасность: прочитайте до включения

**`multipart/form-data` — это CORS «simple request».** Кросс-доменная HTML-форма может отправить такой POST с cookies пользователя и без preflight. Обязательный `Content-Type: application/json`, введённый в 5.0, как раз и закрывает этот CSRF-вектор ([security_hardening.ru.md](./security_hardening.ru.md)), а `multipart.enabled: true` намеренно делает в нём отверстие — для методов с `acceptsMultipart: true` и только для них.

Именно поэтому переключатель выключен по умолчанию и метод обязан согласиться отдельно. Прежде чем включать, убедитесь, что для затронутых методов верно хотя бы одно:

- **Аутентификация не ездит в cookies.** Заголовок `Authorization` / `X-AUTH-TOKEN` не входит в CORS-safelist, поэтому кросс-доменная форма его не отправит и запрос придёт неаутентифицированным. Это самый простой ответ — и обычно JSON-RPC API так и устроен.
- **Session-cookie помечена `SameSite=Lax` или `Strict`.** Lax — значение по умолчанию в Symfony (`framework.session.cookie_samesite: lax`), и оно не даёт кросс-доменному POST формы унести сессию.
- **Метод проверяет CSRF-токен**, который читает из `params`, — значение, которого кросс-доменная страница знать не может.

Оставляйте `acceptsMultipart: true` только на методах, которые действительно принимают файлы. Каждый такой метод — метод, в который сторонняя страница может попробовать отправить POST; каждый метод без флага отклоняется до запуска.

## Альтернативы, о которых стоит знать

**Base64 внутри JSON** работает уже сегодня и без всякой настройки — и это верный ответ для небольших полезных нагрузок: иконка, подпись, миниатюра. Объявите параметр типом `string` и раскодируйте его в методе. Плата — на треть больше байт в канале и вся нагрузка в памяти, из-за чего этот путь не годится для больших файлов.

**Двухшаговая загрузка** — отдельный endpoint возвращает id файла, а RPC-метод ссылается на этот id — остаётся правильным паттерном для очень больших файлов, докачки и файлов, общих для нескольких вызовов. Ей нужны хранилище и политика времени жизни промежуточного файла, то есть инфраструктура, владеть которой бандлу не следует.
