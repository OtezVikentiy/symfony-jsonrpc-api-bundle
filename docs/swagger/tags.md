# Swagger - using tags

---

## Description

In order to combine work with entities into groups, you can use tags for this. Let's say 
you have API endpoints GetUser, CreateUser, DeleteUser and you want them to be displayed 
in one group in swagger. This can be done as follows:

---

```php
<?php
// src/RPC/V1/GetUser.php

namespace App\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;

#[JsonRPCAPI(
    methodName: 'GetUser',
    type: 'POST',
    tags: ['user'] // <--- this will group these methods in one group
)]
class GetUserMethod
{
    // ... some logic here ...
}
```
```php
<?php
// src/RPC/V1/CreateUser.php

namespace App\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;

#[JsonRPCAPI(
    methodName: 'CreateUser',
    type: 'POST',
    tags: ['user'] // <--- this will group these methods in one group
)]
class CreateUserMethod
{
    // ... some logic here ...
}
```
```php
<?php
// src/RPC/V1/DeleteUser.php

namespace App\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;

#[JsonRPCAPI(
    methodName: 'DeleteUser',
    type: 'POST',
    tags: ['user'] // <--- this will group these methods in one group
)]
class DeleteUserMethod
{
    // ... some logic here ...
}
```