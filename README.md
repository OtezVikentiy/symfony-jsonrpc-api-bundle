# OtezVikentiy Symfony Json RPC API Bundle

The bundle allows you to quickly and conveniently deploy JSON RPC API applications based on the Symfony 6 framework.

## Features
- versioning
- easy integration
- compatible with https://www.jsonrpc.org/specification

gitflick: https://gitflic.ru/project/otezvikentiy/json-rpc-api

# Bundle installation

Require the bundle as a dependency.

```bash
$ composer require otezvikentiy/json-rpc-api
```

Enable it in your application Kernel.

```php
<?php
// config/bundles.php
return [
    //...
    OV\JsonRPCAPIBundle\OVJsonRPCAPIBundle::class => ['all' => true],
];
```

Import routing and configure services section.

```yaml
# config/routes/ov_json_rpc_api.yaml
ov_json_rpc_api:
   resource: '@OVJsonRPCAPIBundle/config/routes/routes.yaml'
```

```yaml
# config/services.yaml
services:
    App\RPC\V1\:
        resource: '../src/RPC/V1/{*Method.php}'
        tags:
            - { name: ov.rpc.method, namespace: App\RPC\V1\, version: 1 }
```

---

# Test-Drive

## Create directories and files

During the installation process, we defined the `src/RPC/V1/{*Method.php}` directory in the services and marked with
tags in it all the classes ending in `*Method.php` - these will be our API endpoints.

```
└── src
    └── RPC
        └── V1
            └── getProducts
                ├── GetProductsRequest.php
                └── GetProductsResponse.php
            └── GetProductsMethod.php
```

Create the following classes:

```php
<?php

namespace App\RPC\V1\getProducts;

class GetProductsRequest
{
    private int $id;
    private string $title;

    /**
     * @param int $id
     */
    public function __construct(int $id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
```
```php
<?php

namespace App\RPC\V1\getProducts;

class GetProductsResponse
{
    private bool $success;
    private string $title;

    /**
     * @param string $title
     * @param bool $success
     */
    public function __construct(string $title, bool $success = true)
    {
        $this->success = $success;
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @param bool $success
     */
    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }
}
```
```php
<?php

namespace App\RPC\V1;

use OV\JsonRPCAPIBundle\Core\Annotation\JsonRPCAPI;
use App\RPC\V1\getProducts\GetProductsRequest;
use App\RPC\V1\getProducts\GetProductsResponse;

/**
 * @JsonRPCAPI(methodName = "getProducts")
 */
#[JsonRPCAPI(methodName: 'getProducts')]
class GetProductsMethod
{
    /**
     * @param GetProductsRequest $request
     * @return GetProductsResponse
     */
    public function call(GetProductsRequest $request): GetProductsResponse
    {
        return new GetProductsResponse($request->getTitle().'OLOLOLOLO');
    }
}
```
And now you can execute curl request like this:

```bash
curl --header "Content-Type: application/json" --request POST --data '{"jsonrpc": "2.0","method": "getProducts","params": {"title": "AZAZAZA"},"id": 1}' http://localhost/api/v1
```
And the answer will be something like this:

```bash
{"jsonrpc":"2.0","result":{"title":"AZAZAZAOLOLOLOLO","success":true},"id":null}
```
In total, in order to create a new endpoint for your RPC API, you only need to add 3 classes - this is the method itself and the folder with the request and response.


---

## Security

You can also add token authorization like this:
1) create src/Entity/ApiToken.php
```php
<?php

namespace App\Entity;

use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ApiToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(type: 'string', length: 500, nullable: false)]
    private string $token;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    private DateTimeInterface $expiresAt;

    #[ORM\ManyToOne(inversedBy: 'apiTokens')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return ApiToken
     */
    public function setId(int $id): ApiToken
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @param string $token
     *
     * @return ApiToken
     */
    public function setToken(string $token): ApiToken
    {
        $this->token = $token;

        return $this;
    }

    /**
     * @return DateTimeInterface
     */
    public function getExpiresAt(): DateTimeInterface
    {
        return $this->expiresAt;
    }

    /**
     * @param DateTimeInterface $expiresAt
     *
     * @return ApiToken
     */
    public function setExpiresAt(DateTimeInterface $expiresAt): ApiToken
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @param User $user
     *
     * @return ApiToken
     */
    public function setUser(User $user): ApiToken
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return (new DateTime())->getTimestamp() > $this->expiresAt->getTimestamp();
    }
}
```

2) create src/Security/ApiKeyAuthenticator.php
```php
<?php

namespace App\Security;

use App\Entity\ApiToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiKeyAuthenticator extends AbstractAuthenticator
{
    /**
     * @param EntityManagerInterface $em
     */
    public function __construct(
        private readonly EntityManagerInterface $em
    ){
    }

    /**
     * @param Request $request
     *
     * @return bool
     */
    public function supports(Request $request): bool
    {
        return str_contains($request->getRequestUri(), '/api/v');
    }

    /**
     * @param Request $request
     *
     * @return Passport
     */
    public function authenticate(Request $request): Passport
    {
        $apiToken = $request->headers->get('X-AUTH-TOKEN');
        if (null === $apiToken) {
            throw new CustomUserMessageAuthenticationException('No API token provided');
        }

        $apiTokenEntity = $this->em->getRepository(ApiToken::class)->findOneBy(['token' => $apiToken]);
        if (is_null($apiTokenEntity)) {
            throw new CustomUserMessageAuthenticationException('No API token provided');
        }

        return new SelfValidatingPassport(new UserBadge(
            $apiTokenEntity->getUser()->getId(),
            function () use ($apiTokenEntity) {
                return $this->em->getRepository(User::class)->find($apiTokenEntity->getUser()->getId());
            }
        ));
    }

    /**
     * @param Request        $request
     * @param TokenInterface $token
     * @param string         $firewallName
     *
     * @return Response|null
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    /**
     * @param Request                 $request
     * @param AuthenticationException $exception
     *
     * @return Response|null
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData())
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }
}
```

3) add new firewall to security section security.firewalls.api...

```yaml
security:
    # https://symfony.com/doc/current/security.html#registering-the-user-hashing-passwords
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'
    # https://symfony.com/doc/current/security.html#loading-the-user-the-user-provider
    providers:
        # used to reload user from session & other features (e.g. switch_user)
        app_user_provider:
            entity:
                class: App\Entity\User
                property: email
    firewalls:
        api:
            provider: app_user_provider
            custom_authenticators:
                - App\Security\ApiKeyAuthenticator
```

4) run migration to create a table and add a token for a user - that's it! It is a standard way to create token authentication in symfony: https://symfony.com/doc/current/security/custom_authenticator.html