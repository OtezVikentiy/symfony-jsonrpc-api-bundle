# Security - Self-written token Auth

---

## Description

If you do not need any complex and sophisticated mechanics with updated tokens, then
the simplest and easiest implementation option will be described in this example. Feel
free to modify it at your own discretion and desire.

---

1) create ``src/Entity/ApiToken.php``

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

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false)]
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

2) create ``src/Security/ApiKeyAuthenticator.php``

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

4) run migration to create a table and add a token for a user - that's it! It is a standard 
way to create token authentication in symfony: https://symfony.com/doc/current/security/custom_authenticator.html

5) Now you are able to add X-AUTH-TOKEN to headers of your requests and authorize requests this way