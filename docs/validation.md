# Валидация параметров

Бандл автоматически валидирует входящие параметры запроса на основе типов свойств Request-класса. Дополнительная конфигурация не требуется.

---

## Как это работает

При регистрации метода бандл анализирует Request-класс через Reflection и строит набор валидаторов на основе типов свойств:

```php
class Request
{
    private int $id;           // обязательный, тип int
    private string $title;     // обязательный, тип string
    private ?string $email;    // необязательный (nullable), тип string
    private int $page = 1;     // необязательный (есть default), тип int
}
```

При поступлении запроса параметры проверяются через `Symfony Validator` с constraint `Assert\Type`.

---

## Обязательные vs необязательные параметры

Параметр считается **необязательным** если:
- Тип помечен как nullable (`?string`, `?int`)
- Свойство имеет значение по умолчанию (`private int $page = 1`)

Во всех остальных случаях параметр **обязателен**.

Для необязательных параметров допускается: значение нужного типа, `null`, пустая строка или отсутствие параметра.

---

## Формат ошибки валидации

Если параметры не прошли валидацию, бандл возвращает ошибку с кодом `-32602` (Invalid params):

**Запрос:**
```json
{
    "jsonrpc": "2.0",
    "method": "getProduct",
    "params": {"id": "not_a_number", "title": 12345},
    "id": "1"
}
```

**Ответ:**
```json
{
    "jsonrpc": "2.0",
    "error": {
        "code": -32602,
        "message": "Invalid params. Additional info: [id] - This value should be of type int.\n[title] - This value should be of type string."
    },
    "id": "1"
}
```

---

## Валидация вложенных объектов

Если свойство Request-класса имеет тип другого класса, бандл автоматически создаёт экземпляр через setters и валидирует каждое поле:

```php
class Filter
{
    private int $id;
    private string $title;
    private bool $finished;

    // getters и setters...
}

class Request
{
    private Filter $filter;

    // getter и setter...
}
```

**Запрос:**
```json
{
    "jsonrpc": "2.0",
    "method": "getFilteredData",
    "params": {
        "filter": {"id": 1, "title": "test", "finished": true}
    },
    "id": "1"
}
```

Если в `filter` передать неожиданное поле, бандл вернёт ошибку:

```json
{
    "jsonrpc": "2.0",
    "error": {
        "code": -32602,
        "message": "Invalid params. Additional info: Parameters unknownField is not expected in request."
    },
    "id": "1"
}
```

---

## Поддерживаемые типы

| PHP-тип | Валидируется как |
|---------|-----------------|
| `int` | `int` |
| `string` | `string` |
| `float` | `float` |
| `bool` | `bool` |
| `array` | `array` |
| Класс (`Filter`, `Address`, ...) | Рекурсивная валидация через setters |
