# Базовый класс JsonRpcRequest

`OV\JsonRPCAPIBundle\Core\Request\JsonRpcRequest` — абстрактный класс, от которого можно наследовать ваши Request-классы для получения метода `toArray()`.

---

## Зачем нужен

Метод `toArray()` рекурсивно преобразует объект запроса (включая вложенные объекты и массивы) в ассоциативный массив. Это полезно когда:

- Нужно передать данные запроса в другой сервис в виде массива
- Нужно залогировать содержимое запроса
- Нужно сериализовать запрос для очереди сообщений

---

## Пример

```php
namespace App\RPC\V1\GetProduct;

use OV\JsonRPCAPIBundle\Core\Request\JsonRpcRequest;

class Request extends JsonRpcRequest
{
    private int $id;
    private string $title;

    public function getId(): int { return $this->id; }
    public function setId(int $id): void { $this->id = $id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): void { $this->title = $title; }
}
```

Использование:

```php
public function call(Request $request): Response
{
    $data = $request->toArray();
    // ['id' => 1, 'title' => 'Iphone 15']

    $this->logger->info('Request received', $data);

    // ...
}
```

---

## Вложенные объекты

`toArray()` рекурсивно обрабатывает вложенные объекты:

```php
class Filter
{
    private int $id;
    private string $title;
    // getters, setters...
}

class Request extends JsonRpcRequest
{
    private Filter $filter;
    // getter, setter...
}
```

```php
$request->toArray();
// ['filter' => ['id' => 1, 'title' => 'test']]
```

Объекты, реализующие `DateTimeInterface` (`DateTime` и `DateTimeImmutable`), не раскладываются на свойства — они форматируются в строку ISO 8601 (`DATE_ATOM`). Если вложенный объект имеет собственный метод `toArray()`, он будет вызван.

> **Изменение в 5.0.** Раньше `toArray()` читал свойства напрямую через Reflection, поэтому приватное поле без геттера тоже попадало в результат — а документация предлагает этот метод для логирования запроса, то есть пароль или внутренний токен уходили в лог. Теперь поле экспортируется, если класс его открывает: есть публичный геттер (`getX()`, `isX()` или голый `x()`) либо само свойство публичное — как и в ответе. Приватное без публичного геттера не экспортируется. Взаимные ссылки между Request-DTO раньше приводили к переполнению стека и падению воркера; теперь бросается `JRPCException` с кодом `-32603`, то есть **`toArray()` может выбросить исключение** — учтите это, если вызываете его в блоке логирования.
>
> Сериализация ответа устроена **тем же** механизмом (общий трейт), с одним отличием: вложенный объект со своим `toArray()` в запросе решает свою форму сам, а в ответе — нет, там форму задают геттеры. См. [примечание о геттерах в базовом примере](./examples/base.md#response) и [upgrade-5.0.md](./upgrade-5.0.md).

---

## Когда использовать

| Ситуация | Рекомендация |
|----------|-------------|
| Простой Request с несколькими полями | Наследование не обязательно |
| Нужен `toArray()` для логирования или передачи | Наследуйтесь от `JsonRpcRequest` |
| Сложные вложенные структуры | Наследуйтесь от `JsonRpcRequest` |

Наследование от `JsonRpcRequest` **не обязательно** — бандл работает с любым Request-классом. Это утилитарный базовый класс для удобства.
