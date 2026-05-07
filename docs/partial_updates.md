# Partial updates (JSON Merge Patch)

`OV\JsonRPCAPIBundle\Core\Request\PartialRequestInterface` — opt-in контракт, который позволяет сервис-слою различать «поле не передано в payload» от «поле передано как `null`». Это нужно для PATCH-сценариев, где клиент шлёт только изменённые поля и должен иметь возможность **очистить** поле, передав `null`.

Семантика соответствует [RFC 7396 (JSON Merge Patch)](https://datatracker.ietf.org/doc/html/rfc7396).

---

## Зачем нужен

Типовая реализация Update-метода:

```php
public function call(UpdateUserRequest $request): Response
{
    $user = $this->userRepository->find($request->getId());

    if ($request->getEmail() !== null) {
        $user->setEmail($request->getEmail());
    }
    if ($request->getBio() !== null) {
        $user->setBio($request->getBio());
    }
    // ...
}
```

Здесь скрыта проблема: `null` в DTO означает одновременно две вещи:
- «клиент не передал это поле» — дефолтное значение свойства DTO;
- «клиент явно передал `null`» — фреймворк хранит это в свойстве после set-а.

Сервис не может различить эти случаи. В результате **очистка поля невозможна**: при попытке отправить `{"email": null}` сервис прокинет `null !== null === false` и НЕ вызовет setter.

---

## Решение

DTO реализует `PartialRequestInterface`, и фреймворк трекает, какие поля реально пришли в payload. Сервис использует `wasProvided()`:

```php
public function call(UpdateUserRequest $request): Response
{
    $user = $this->userRepository->find($request->getId());

    if ($request->wasProvided('email')) {
        $user->setEmail($request->getEmail()); // null = очистить
    }
    if ($request->wasProvided('bio')) {
        $user->setBio($request->getBio());
    }
    // ...
}
```

---

## Использование

### Через базовый класс `PartialUpdateRequest`

Самый короткий путь — наследоваться от `PartialUpdateRequest`. Он сразу даёт `toArray()` (от `JsonRpcRequest`), реализацию `PartialRequestInterface` и трейт `TracksProvidedFieldsTrait`.

```php
namespace App\RPC\V1\UpdateUser;

use OV\JsonRPCAPIBundle\Core\Request\PartialUpdateRequest;

class Request extends PartialUpdateRequest
{
    private ?int $id = null;
    private ?string $email = null;
    private ?string $name = null;
    private ?string $bio = null;

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): void { $this->id = $id; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): void { $this->email = $email; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): void { $this->name = $name; }

    public function getBio(): ?string { return $this->bio; }
    public function setBio(?string $bio): void { $this->bio = $bio; }
}
```

### Через интерфейс + трейт

Если базовый класс уже занят, можно собрать вручную:

```php
use OV\JsonRPCAPIBundle\Core\Request\PartialRequestInterface;
use OV\JsonRPCAPIBundle\Core\Request\TracksProvidedFieldsTrait;

class Request implements PartialRequestInterface
{
    use TracksProvidedFieldsTrait;

    // ... поля и геттеры/сеттеры
}
```

### Своя реализация интерфейса

Трейт не обязателен. Можно реализовать `markProvided` / `wasProvided` / `getProvidedFields` вручную, если нужна особая логика хранения.

---

## Семантика payload-а

| Payload | `wasProvided('email')` | `getEmail()` | Поведение в сервисе |
|---|---|---|---|
| `{"email": "new@x.com"}` | `true` | `"new@x.com"` | установить новое значение |
| `{"email": null}` | `true` | `null` | очистить поле |
| `{}` (ключ отсутствует) | `false` | `null` (дефолт) | не трогать поле |

Это в точности соответствует JSON Merge Patch (RFC 7396).

---

## Когда `markProvided` НЕ вызывается

Фреймворк вызывает `markProvided($name)` только когда ключ **реально присутствовал** в raw JSON-RPC payload. В следующих случаях вызов не происходит:

1. **Ключ отсутствует, но в спеке метода есть `defaultValue`.** Свойство DTO будет заполнено дефолтом из спеки, но `wasProvided` вернёт `false` — это значение от бандла, а не от клиента.
2. **Синтетический параметр `params`** (используется для bulk-payload-ов вида `[42, 23]`).
3. **DTO не реализует `PartialRequestInterface`.** Тогда трекинг полностью отключён (BC).

---

## Вложенные DTO

`PartialRequestInterface` работает рекурсивно для вложенных объектов. Если у вас есть DTO с вложенным объектом-аддресом, и оба реализуют интерфейс — трекинг распространяется на оба уровня:

```php
class AddressDto implements PartialRequestInterface
{
    use TracksProvidedFieldsTrait;
    private ?string $city = null;
    private ?string $street = null;
    // ...
}

class UpdateUserRequest extends PartialUpdateRequest
{
    private ?AddressDto $address = null;
    // ...
}
```

Для payload-а `{"address": {"city": "Moscow"}}`:

```php
$request->wasProvided('address');                 // true
$request->getAddress()->wasProvided('city');      // true
$request->getAddress()->wasProvided('street');    // false
```

Это соответствует object-merge-семантике RFC 7396.

---

## Обратная совместимость

- DTO, не реализующие `PartialRequestInterface`, ведут себя ровно как до 3.9. Никаких изменений в поведении или сигнатурах публичных API.
- `instanceof`-проверка короткозамкнута: если DTO без интерфейса — фреймворк не делает лишних вызовов.
- Конфигурационный флаг не нужен: opt-in через интерфейс сам по себе toggle на уровне Request-класса.

---

## Edge-кейсы

### Required-поля
Если поле должно быть обязательным даже в PATCH-сценарии (например, `id` для идентификации записи), используйте обычный `Required` в спеке валидации. `wasProvided` не отменяет валидацию.

### Поля, которые нельзя очищать
Например, пароль пользователя — клиент может его менять, но не очищать. Реализуйте логику в сервисе:

```php
if ($request->wasProvided('password') && $request->getPassword() !== null) {
    $user->setPassword($this->hasher->hash($request->getPassword()));
}
```

### Boolean-поля
`false` — валидное значение, не путайте с `null`. `wasProvided` корректно различает все четыре случая (`true` / `false` / `null` / отсутствие ключа).

### Коллекции
Конвенция:
- `null` (явно) → клиент хочет очистить связи (если бизнес-логика это разрешает).
- `[]` → пустая коллекция (тоже очистка).
- массив значений → полная замена.
- ключ отсутствует → не трогать.

### Audit-log
Метод `getProvidedFields()` возвращает список реально переданных полей — удобно для логирования только настоящих изменений.

---

## Как фреймворк это делает

В `RequestHandler::hydrateRequest()` есть две ветки получения значения:

1. `array_key_exists($name, $baseRequest->getParams())` — ключ в payload. Только в этом случае поле будет помечено через `markProvided`.
2. `array_key_exists('defaultValue', $allParameter)` — фолбэк на дефолт из спеки. Поле НЕ помечается.

Симметричная логика реализована в `prepareParametersFromClass()` для рекурсивной гидратации вложенных DTO.

Подробности — в исходном коде `RequestHandler.php` (методы `hydrateRequest` и `prepareParametersFromClass`).
