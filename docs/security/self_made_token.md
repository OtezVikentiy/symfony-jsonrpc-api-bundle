# Кастомная токенная аутентификация

---

## Описание

Простейший вариант аутентификации через API-токен в HTTP-заголовке.
Подходит для случаев, когда не нужна сложная механика обновления токенов (refresh tokens).

Реализация основана на стандартном Symfony Custom Authenticator.

---

## 1. Создайте Entity для токена

```php
<?php
// src/Entity/ApiToken.php

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

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
    private string $token;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false)]
    private DateTimeInterface $expiresAt;

    #[ORM\ManyToOne(inversedBy: 'apiTokens')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    public function getId(): int { return $this->id; }

    public function getToken(): string { return $this->token; }
    public function setToken(string $token): ApiToken { $this->token = $token; return $this; }

    public function getExpiresAt(): DateTimeInterface { return $this->expiresAt; }
    public function setExpiresAt(DateTimeInterface $expiresAt): ApiToken { $this->expiresAt = $expiresAt; return $this; }

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): ApiToken { $this->user = $user; return $this; }

    public function isValid(): bool
    {
        return (new DateTime())->getTimestamp() < $this->expiresAt->getTimestamp();
    }
}
```

## 2. Создайте Authenticator

```php
<?php
// src/Security/ApiKeyAuthenticator.php

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
    ) {}

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
            throw new CustomUserMessageAuthenticationException('Invalid API token');
        }

        if (!$apiTokenEntity->isValid()) {
            throw new CustomUserMessageAuthenticationException('API token expired');
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
        return new JsonResponse(
            ['message' => strtr($exception->getMessageKey(), $exception->getMessageData())],
            Response::HTTP_UNAUTHORIZED
        );
    }
}
```

## 3. Настройте firewall

```yaml
# config/packages/security.yaml
security:
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'
    providers:
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

## 4. Примените миграцию и создайте токен

```bash
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

После создания записи в таблице `api_token` для нужного пользователя, добавляйте токен в заголовок запросов:

```bash
curl -X POST http://localhost/api/v1 \
  -H "Content-Type: application/json" \
  -H "X-AUTH-TOKEN: your_token_here" \
  -d '{"jsonrpc": "2.0", "method": "getProduct", "params": {"id": 1}, "id": 1}'
```

## Дополнительно

- Документация Symfony: [Custom Authenticator](https://symfony.com/doc/current/security/custom_authenticator.html)
- Для более сложных сценариев (refresh tokens, JWT) см. [JWT-аутентификация](./jwt_bundle.md)
