# OtezVikentiy Symfony Json RPC API Bundle

The bundle allows you to quickly and conveniently deploy JSON RPC API applications based on the Symfony 6 framework.

## Features
- easy api versioning
- easy bundle installation
- compatible with attributes
- compatible with POST, GET, PUT, PATCH, DELETE requests
- fully compatible with https://www.jsonrpc.org/specification
- swagger openapi out of the box
- callbacks

github: https://github.com/OtezVikentiy/symfony-jsonrpc-api-bundle

Instructions: https://otezvikentiy.tech/articles/symfony-json-rpc-api-bundle-prostoe-api-so-vsem-neobhodimym

# Bundle installation

1) Require the bundle as a dependency.

```bash
$ composer require otezvikentiy/json-rpc-api
```

2) Enable it in your application Kernel. ( not required if using flex )

```php
<?php
// config/bundles.php
return [
    //...
    OV\JsonRPCAPIBundle\OVJsonRPCAPIBundle::class => ['all' => true],
];
```

3) Create / update these config files
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

```yaml
# config/packages/ov_json_rpc_api.yaml
ov_json_rpc_api:
    access_control_allow_origin_list:
        - localhost
        - api.localhost
        - *
    swagger:
        api_v1:
            api_version: '1'
            base_path: '%env(string:OV_JSON_RPC_API_BASE_URL)%'
            auth_token_name: 'X-AUTH-TOKEN'
            auth_token_test_value: '%env(string:OV_JSON_RPC_API_AUTH_TOKEN)%' #set blank for prod environment
            info:
                title: 'Some awesome api title here'
                description: 'Some description about your api here would be appreciated if you like'
                terms_of_service_url: 'https://terms_of_service_url.test/url'
                contact:
                    name: 'John Doe'
                    url: 'https://john-doe.test'
                    email: 'john.doe@john-doe.test'
                license: 'MIT license'
                licenseUrl: 'https://john-doe.test/mit-license'
```

```dotenv
# .env
###> otezvikentiy/json-rpc-api ###
OV_JSON_RPC_API_SWAGGER_PATH=public/openapi/
OV_JSON_RPC_API_BASE_URL=http://localhost
OV_JSON_RPC_API_AUTH_TOKEN=2f1f6aee7d994528fde6e47a493cc097
###< otezvikentiy/json-rpc-api ###
```

---

# Test-Drive

## Create directories and files

During the installation process, we defined the `src/RPC/V1/{*Method.php}` directory in the services and marked with
tags in it all the classes ending in `*Method.php` - these will be our API endpoints.

See examples in directory `examples`.

---

## Swagger

If you wish to generate openapi swagger yaml file - then run this command:

```bash
bin/console ov:swagger:generate
```

It would generate a swagger file ( example public/openapi/api_v1.yaml ) which you can use in your swagger instance

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

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): ApiToken
    {
        $this->id = $id;

        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): ApiToken
    {
        $this->token = $token;

        return $this;
    }

    public function getExpiresAt(): DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(DateTimeInterface $expiresAt): ApiToken
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): ApiToken
    {
        $this->user = $user;

        return $this;
    }

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
    public function __construct(
        private readonly EntityManagerInterface $em
    ){
    }

    public function supports(Request $request): bool
    {
        return str_contains($request->getRequestUri(), '/api/v');
    }

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

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

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
            pattern: ^/api
            provider: app_user_provider
            custom_authenticators:
                - App\Security\ApiKeyAuthenticator
```

4) run migration to create a table and add a token for a user - that's it! It is a standard way to create token authentication in symfony: https://symfony.com/doc/current/security/custom_authenticator.html

5) Now you are able to add X-AUTH-TOKEN to headers of your requests and authorize requests this way