<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Logging;

use OV\JsonRPCAPIBundle\Core\Logging\SensitiveDataMasker;
use OV\JsonRPCAPIBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;

/**
 * Pins what the shipped defaults do and do not hide. The list protects an operator who turned
 * logging on without reading the masking documentation, so both halves matter: a credential name
 * missing from it ends up in the log, and an ordinary field wrongly caught by it makes the log
 * useless for debugging. The second half is what stops the list from being widened carelessly.
 */
final class DefaultMaskingPatternsTest extends TestCase
{
    public static function credentialFieldNames(): array
    {
        return array_map(static fn (string $name): array => [$name], [
            'password', 'user_password', 'passwd', 'pwd', 'pwd_hash',
            'secret', 'clientSecret', 'token', 'refresh_token', 'jwt',
            'apiKey', 'api_key', 'access_key', 'private_key',
            'Authorization', 'credentials', 'session_id', 'cookie', 'auth_cookie',
            'cardNumber', 'cvv', 'cvc', 'ssn', 'certificate',
            'bearer', 'bearerToken', 'auth', 'auth_header',
            'signature', 'sign', 'sign_key', 'hmac', 'hmacSignature',
            'pin', 'pin_code', 'otp', 'otp_code',
            'salt', 'password_salt', 'hash', 'hash_value', 'iban', 'pan', 'pan_number',
        ]);
    }

    /**
     * Names that share a prefix or a substring with a credential name and carry nothing sensitive.
     */
    public static function ordinaryFieldNames(): array
    {
        return array_map(static fn (string $name): array => [$name], [
            'id', 'name', 'email', 'title', 'count', 'status', 'description',
            'panel', 'signal', 'designer', 'authorName', 'pinned', 'hashtag', 'painting',
        ]);
    }

    #[DataProvider('credentialFieldNames')]
    public function testCredentialFieldIsMaskedByDefault(string $field): void
    {
        self::assertSame(['***'], array_values($this->defaultMasker()->mask([$field => 'the-value'])));
    }

    #[DataProvider('ordinaryFieldNames')]
    public function testOrdinaryFieldSurvivesTheDefaults(string $field): void
    {
        self::assertSame(['the-value'], array_values($this->defaultMasker()->mask([$field => 'the-value'])));
    }

    /**
     * Spec section 4.2 allows params to be an Array, and the elements of one are keyed by position.
     * Masking selects on key names, so there is nothing for it to select on - a positional secret
     * reaches the log whatever the pattern list says. Recorded here so the gap is a known one.
     */
    public function testPositionalParametersAreNotMaskedAtAll(): void
    {
        $masked = $this->defaultMasker()->mask(['params' => ['alice', 'hunter2']]);

        self::assertSame(['params' => ['alice', 'hunter2']], $masked);
    }

    private function defaultMasker(): SensitiveDataMasker
    {
        /** @var list<string> $patterns */
        $patterns = (new ReflectionClass(Configuration::class))->getConstant('DEFAULT_MASKING_KEY_PATTERNS');

        return new SensitiveDataMasker($patterns, '***', new NullLogger());
    }
}
