# Roles based on built-in Symfony Security example

---

## Description

This bundle supports several options for delimiting access to API endpoints. More
global and more classic via security.yaml and more narrowly targeted for each
individual API method.

Let's imagine that we have several APIs intended for different groups of people and consider
examples of how to separate access to the API for them.

---

In the main symfony security file, everything is done in the classic way according to the Symfony documentation.
Nothing special here.

```yaml
# config/packages/security.yaml
security:
    #...
    access_control:
        - {path: /api/login, roles: PUBLIC_ACCESS}
        - {path: /api/register, roles: PUBLIC_ACCESS}
        - {path: /api/v1, roles: ROLE_ADMIN, ROLE_SUPER_ADMIN}
        - {path: /api/v2, roles: ROLE_OPERATIONIST, ROLE_INSURANCE}
        - {path: /api, roles: IS_AUTHENTICATED_FULLY}
```

Let's imagine that we have several API endpoints, for example:

- /api/v1
  - getUsers
  - getAdminUsers
  - createUser
- /api/v2
  - getClients
  - getInsuranceList

Accordingly, there will be several methods of the following type. Note 
the roles parameter in the JsonRPCAPI attribute.

```php
<?php
// src/RPC/V1/GetUsers.php

namespace App\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;

#[JsonRPCAPI(
    methodName: 'GetUsers',
    type: 'POST',
    roles: [User::ROLE_ADMIN]
)]
class GetUsersMethod
{
    // ... some logic here ...
}
```

```php
<?php
// src/RPC/V1/GetAdminUsers.php

namespace App\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;

#[JsonRPCAPI(
    methodName: 'GetAdminUsers',
    type: 'POST',
    roles: [User::ROLE_SUPER_ADMIN]
)]
class GetAdminUsersMethod
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
    roles: [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN]
)]
class CreateUserMethod
{
    // ... some logic here ...
}
```

```php
<?php
// src/RPC/V2/GetClients.php

namespace App\RPC\V2;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;

#[JsonRPCAPI(
    methodName: 'GetClients',
    type: 'POST',
    roles: [User::ROLE_OPERATIONIST, User::ROLE_INSURANCE]
)]
class GetClientsEndpoint
{
    // ... some logic here ...
}
```

```php
<?php
// src/RPC/V2/GetInsuranceList.php

namespace App\RPC\V2;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;

#[JsonRPCAPI(
    methodName: 'GetInsuranceList',
    type: 'POST',
    roles: [User::ROLE_INSURANCE]
)]
class GetInsuranceListEndpoint
{
    // ... some logic here ...
}
```

This way we have the ability to differentiate roles and access to specific API 
endpoints for specific user roles.