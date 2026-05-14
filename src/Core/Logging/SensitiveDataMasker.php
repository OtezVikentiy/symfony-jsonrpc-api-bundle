<?php
/*
 * This file is part of the OtezVikentiy Json RPC API package.
 *
 * (c) Leonid Groshev <otezvikentiy@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OV\JsonRPCAPIBundle\Core\Logging;

use Psr\Log\LoggerInterface;

final class SensitiveDataMasker implements SensitiveDataMaskerInterface
{
    private const INVALID_PATTERN_WARNING = 'JsonRpcLogging: invalid sensitive-data masking regex skipped.';
    private const WARNING_CONTEXT_KEY_PATTERN = 'pattern';

    /** @var array<string, true> */
    private array $invalidPatternsWarned = [];

    public function __construct(
        private readonly array $keyPatterns,
        private readonly string $placeholder,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function mask(array $data): array
    {
        if ($this->keyPatterns === []) {
            return $data;
        }

        $result = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->keyMatches($key)) {
                $result[$key] = $this->placeholder;
                continue;
            }

            $result[$key] = is_array($value) ? $this->mask($value) : $value;
        }

        return $result;
    }

    private function keyMatches(string $key): bool
    {
        foreach ($this->keyPatterns as $pattern) {
            $matched = @preg_match($pattern, $key);
            if ($matched === false) {
                $this->warnInvalidPatternOnce($pattern);
                continue;
            }
            if ($matched === 1) {
                return true;
            }
        }

        return false;
    }

    private function warnInvalidPatternOnce(string $pattern): void
    {
        if (isset($this->invalidPatternsWarned[$pattern])) {
            return;
        }

        $this->invalidPatternsWarned[$pattern] = true;
        $this->logger->warning(
            self::INVALID_PATTERN_WARNING,
            [self::WARNING_CONTEXT_KEY_PATTERN => $pattern],
        );
    }
}
