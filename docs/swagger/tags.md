[English](tags.md) · [Русский](tags.ru.md)

# Swagger — tags for grouping

---

## What it does

Tags group related API methods in the Swagger documentation. Every method dealing with users, for instance, can be collected under a `user` group.

Tags are declared through the `tags` parameter of the `#[JsonRPCAPI]` attribute. One method may carry several.

---

## Example

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

All three methods appear in Swagger UI under the **user** group.

## Several tags

A method may belong to more than one group:

```php
#[JsonRPCAPI(
    methodName: 'GetUserOrders',
    type: 'POST',
    tags: ['user', 'orders']
)]
```

## Generating the documentation

```bash
bin/console ov:swagger:generate
```

Output: `public/openapi/api_v1.yaml`

## Grouping paths (group)

The `group` parameter sets a path prefix in Swagger. It is a convenient way to organise a large number of methods visually:

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

The paths in Swagger UI become:
```
/products/get_product
/products/create_product
/users/get_user
```

Without `group`, every path stays at the root:
```
/get_product
/create_product
/get_user
```

> `group` and `tags` are different things. `tags` group methods visually in Swagger UI (collapsible sections). `group` shapes the hierarchy of URL paths.

## Schema names in components/schemas

A method's response schema is named `{methodName}Response` (the method `getProduct` gives the schema `getProductResponse`), and its parameter schema `{methodName}Request`. Nested DTOs are named after the full class name with `\` → `.` (see [Swagger — describing arrays](./array.md)). Neither naming scheme can collide with another schema: before 5.0 the short class name was used, and two DTOs sharing one — both named `Response` but living in different `RPC/V1/<Method>/` namespaces, which is the typical project layout, see [the file layout](../examples/base.md#file-layout) — overwrote each other under the same `components/schemas` key.

## Hiding a method from Swagger

When a method should stay out of the Swagger documentation — an internal or test method, say — use `ignoreInSwagger`:

```php
#[JsonRPCAPI(
    methodName: 'internalHealthCheck',
    type: 'POST',
    ignoreInSwagger: true
)]
```
