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

Объекты `DateTime` не раскладываются на свойства, а возвращаются как есть. Если вложенный объект имеет собственный метод `toArray()`, он будет вызван.

---

## Когда использовать

| Ситуация | Рекомендация |
|----------|-------------|
| Простой Request с несколькими полями | Наследование не обязательно |
| Нужен `toArray()` для логирования или передачи | Наследуйтесь от `JsonRpcRequest` |
| Сложные вложенные структуры | Наследуйтесь от `JsonRpcRequest` |

Наследование от `JsonRpcRequest` **не обязательно** — бандл работает с любым Request-классом. Это утилитарный базовый класс для удобства.
