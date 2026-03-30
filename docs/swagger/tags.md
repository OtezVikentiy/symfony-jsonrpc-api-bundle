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

## Скрытие метода из Swagger

Если метод не должен попадать в Swagger-документацию (например, служебный или тестовый), используйте `ignoreInSwagger`:

```php
#[JsonRPCAPI(
    methodName: 'internalHealthCheck',
    type: 'POST',
    ignoreInSwagger: true
)]
```
