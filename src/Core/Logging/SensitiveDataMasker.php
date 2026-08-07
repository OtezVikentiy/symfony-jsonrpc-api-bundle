<?php

declare(strict_types=1);
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
    private const MERGE_DELIMITER = "\x01";

    /** @var array<string, true> */
    private array $invalidPatternsWarned = [];

    /**
     * One alternation regex per distinct flag set, combining every pattern that could be merged safely.
     *
     * @var array<int, string>
     */
    private readonly array $mergedPatterns;

    /**
     * Patterns that failed validation or could not be merged, checked individually as before.
     *
     * @var array<int, string>
     */
    private readonly array $individualPatterns;

    public function __construct(
        private readonly array $keyPatterns,
        private readonly string $placeholder,
        private readonly LoggerInterface $logger,
    ) {
        [$this->mergedPatterns, $this->individualPatterns] = $this->compilePatternGroups($keyPatterns);
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
        foreach ($this->mergedPatterns as $mergedPattern) {
            if (@preg_match($mergedPattern, $key) === 1) {
                return true;
            }
        }

        foreach ($this->individualPatterns as $pattern) {
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

    /**
     * Groups patterns by their delimiter flags and joins each group into a single alternation,
     * so matching a key costs one preg_match call per flag set instead of one per pattern.
     *
     * Patterns that fail validation, or whose delimiter/flags cannot be parsed safely, are left
     * out of the merge and handled one by one exactly as before.
     *
     * @param array<int, mixed> $patterns config values pass through Symfony's scalarPrototype(),
     *                                     so an element is not guaranteed to be a string
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function compilePatternGroups(array $patterns): array
    {
        $groupsByFlags = [];
        $individualPatterns = [];

        foreach ($patterns as $pattern) {
            if (!is_string($pattern)) {
                $this->warnInvalidPatternOnce(var_export($pattern, true));
                continue;
            }

            if (@preg_match($pattern, '') === false) {
                $individualPatterns[] = $pattern;
                continue;
            }

            $parsed = self::parsePattern($pattern);
            if ($parsed === null) {
                $individualPatterns[] = $pattern;
                continue;
            }

            [$body, $flags] = $parsed;
            $groupsByFlags[$flags]['bodies'][] = $body;
            $groupsByFlags[$flags]['patterns'][] = $pattern;
        }

        $mergedPatterns = [];
        foreach ($groupsByFlags as $flags => $group) {
            $mergedPattern = self::MERGE_DELIMITER
                . '(?:' . implode(')|(?:', $group['bodies']) . ')'
                . self::MERGE_DELIMITER . $flags;

            if (@preg_match($mergedPattern, '') === false) {
                array_push($individualPatterns, ...$group['patterns']);
                continue;
            }

            $mergedPatterns[] = $mergedPattern;
        }

        return [$mergedPatterns, $individualPatterns];
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private static function parsePattern(string $pattern): ?array
    {
        if ($pattern === '') {
            return null;
        }

        $delimiter = $pattern[0];
        if ($delimiter === self::MERGE_DELIMITER || ctype_alnum($delimiter) || $delimiter === '\\') {
            return null;
        }

        $closingDelimiter = match ($delimiter) {
            '(' => ')',
            '{' => '}',
            '[' => ']',
            '<' => '>',
            default => $delimiter,
        };

        $lastPos = strrpos($pattern, $closingDelimiter);
        if ($lastPos === false || $lastPos === 0) {
            return null;
        }

        $body = substr($pattern, 1, $lastPos - 1);
        $flags = substr($pattern, $lastPos + 1);

        if ($flags !== '' && preg_match('/^[a-zA-Z]*$/', $flags) !== 1) {
            return null;
        }

        if (str_contains($body, self::MERGE_DELIMITER)) {
            return null;
        }

        return [$body, self::normalizeFlags($flags)];
    }

    private static function normalizeFlags(string $flags): string
    {
        $chars = str_split($flags);
        sort($chars);

        return implode('', array_unique($chars));
    }
}
