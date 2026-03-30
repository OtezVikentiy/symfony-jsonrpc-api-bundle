# Ролевой доступ

---

## Описание

Бандл поддерживает два уровня контроля доступа:

1. **Глобальный** — через `security.yaml` Symfony (ограничение доступа к URL `/api/v1`, `/api/v2` и т.д.)
2. **На уровне метода** — через параметр `roles` в атрибуте `#[JsonRPCAPI]` (ограничение доступа к конкретному JSON-RPC методу)

При отсутствии нужной роли бандл возвращает HTTP 403 с телом `"Access not allowed"`.

---

## Глобальный контроль через security.yaml

Стандартный подход Symfony для ограничения доступа к URL:

```yaml
# config/packages/security.yaml
security:
    #...
    access_control:
        - {path: /api/login, roles: PUBLIC_ACCESS}
        - {path: /api/register, roles: PUBLIC_ACCESS}
        - {path: /api/v1, roles: [ROLE_ADMIN, ROLE_SUPER_ADMIN]}
        - {path: /api/v2, roles: [ROLE_OPERATIONIST, ROLE_INSURANCE]}
        - {path: /api, roles: IS_AUTHENTICATED_FULLY}
```

## Контроль на уровне метода

Параметр `roles` в атрибуте `#[JsonRPCAPI]` позволяет указать, какие роли имеют доступ к конкретному методу. Если у пользователя есть **хотя бы одна** из указанных ролей, доступ разрешён.

```php
<?php

namespace App\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;

#[JsonRPCAPI(
    methodName: 'GetUsers',
    type: 'POST',
    roles: ['ROLE_ADMIN']
)]
class GetUsersMethod
{
    // Доступ только для ROLE_ADMIN
}
```

```php
#[JsonRPCAPI(
    methodName: 'GetAdminUsers',
    type: 'POST',
    roles: ['ROLE_SUPER_ADMIN']
)]
class GetAdminUsersMethod
{
    // Доступ только для ROLE_SUPER_ADMIN
}
```

```php
#[JsonRPCAPI(
    methodName: 'CreateUser',
    type: 'POST',
    roles: ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN']
)]
class CreateUserMethod
{
    // Доступ для ROLE_SUPER_ADMIN ИЛИ ROLE_ADMIN
}
```

## Версионирование и роли

Разные версии API могут обслуживать разные группы пользователей:

```php
<?php
// src/RPC/V2/GetClientsMethod.php

namespace App\RPC\V2;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;

#[JsonRPCAPI(
    methodName: 'GetClients',
    type: 'POST',
    roles: ['ROLE_OPERATIONIST', 'ROLE_INSURANCE']
)]
class GetClientsMethod
{
    // Доступ через /api/v2 для операционистов и страховщиков
}
```

```php
<?php
// src/RPC/V2/GetInsuranceListMethod.php

namespace App\RPC\V2;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;

#[JsonRPCAPI(
    methodName: 'GetInsuranceList',
    type: 'POST',
    roles: ['ROLE_INSURANCE']
)]
class GetInsuranceListMethod
{
    // Доступ через /api/v2 только для страховщиков
}
```

## Методы без ограничений

Если параметр `roles` не указан или передан пустой массив, доступ к методу не ограничен (на уровне бандла). Ограничения `security.yaml` всё ещё применяются.

```php
#[JsonRPCAPI(methodName: 'publicMethod', type: 'POST')]
class PublicMethod
{
    // Доступ для всех авторизованных пользователей (в зависимости от security.yaml)
}
```
