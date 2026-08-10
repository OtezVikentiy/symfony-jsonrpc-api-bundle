[English](tags.md) · [Русский](tags.ru.md)

# Swagger — теги для группировки

---

## Описание

Теги позволяют группировать связанные API-методы в Swagger-документации. Например, все методы для работы с пользователями можно объединить в группу `user`.

Теги задаются через параметр `tags` в атрибуте `#[JsonRPCAPI]`. Один метод может иметь несколько тегов.

---

## Пример

```php
<?php
// src/RPC/V1/GetUserMethod.php

namespace App\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;

#[JsonRPCAPI(
    methodName: 'GetUser',
    type: 'POST',
    tags: ['user']
)]
class GetUserMethod
{
    // ...
}
```

```php
<?php
// src/RPC/V1/CreateUserMethod.php

namespace App\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;

#[JsonRPCAPI(
    methodName: 'CreateUser',
    type: 'POST',
    tags: ['user']
)]
class CreateUserMethod
{
    // ...
}
```

```php
<?php
// src/RPC/V1/DeleteUserMethod.php

namespace App\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;

#[JsonRPCAPI(
    methodName: 'DeleteUser',
    type: 'POST',
    tags: ['user']
)]
class DeleteUserMethod
{
    // ...
}
```

Все три метода будут отображаться в Swagger UI под группой **user**.

## Несколько тегов

Метод может принадлежать нескольким группам:

```php
#[JsonRPCAPI(
    methodName: 'GetUserOrders',
    type: 'POST',
    tags: ['user', 'orders']
)]
```

## Генерация документации

```bash
bin/console ov:swagger:generate
```

Результат: `public/openapi/api_v1.yaml`

## Группировка путей (group)

Параметр `group` задаёт префикс пути в Swagger. Это удобно для визуальной организации большого количества методов:

```php
#[JsonRPCAPI(
    methodName: 'GetProduct',
    type: 'POST',
    tags: ['products'],
    group: 'products'
)]
class GetProductMethod { /* ... */ }

#[JsonRPCAPI(
    methodName: 'CreateProduct',
    type: 'POST',
    tags: ['products'],
    group: 'products'
)]
class CreateProductMethod { /* ... */ }

#[JsonRPCAPI(
    methodName: 'GetUser',
    type: 'POST',
    tags: ['users'],
    group: 'users'
)]
class GetUserMethod { /* ... */ }
```

В Swagger UI пути будут:
```
/products/get_product
/products/create_product
/users/get_user
```

Без `group` все пути остаются в корне:
```
/get_product
/create_product
/get_user
```

> `group` и `tags` — разные вещи. `tags` группируют методы визуально в Swagger UI (сворачиваемые секции). `group` формирует иерархию URL-путей.

## Имена схем в components/schemas

Схема ответа метода называется `{methodName}Response` (например, метод `getProduct` → схема `getProductResponse`), схема параметров — `{methodName}Request`. Вложенные DTO именуются по полному имени класса с `\` → `.` (см. [Swagger — описание массивов](./array.ru.md)). Оба варианта именования гарантированно не сталкиваются с другой схемой: до 5.0 использовалось короткое имя класса, и два DTO с одинаковым коротким именем (например, оба называются `Response`, но лежат в разных `RPC/V1/<Method>/` неймспейсах — типичная структура проекта, см. [структуру файлов](../examples/base.ru.md#структура-файлов)) затирали друг друга в одном и том же ключе `components/schemas`.

## Скрытие метода из Swagger

Если метод не должен попадать в Swagger-документацию (например, служебный или тестовый), используйте `ignoreInSwagger`:

```php
#[JsonRPCAPI(
    methodName: 'internalHealthCheck',
    type: 'POST',
    ignoreInSwagger: true
)]
```
