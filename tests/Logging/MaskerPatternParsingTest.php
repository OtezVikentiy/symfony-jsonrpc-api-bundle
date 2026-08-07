<?php

declare(strict_types=1);

namespace OV\JsonRPCAPIBundle\Tests\Logging;

use OV\JsonRPCAPIBundle\Core\Logging\SensitiveDataMasker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Patterns sharing a flag set are merged into one alternation so matching a key costs one
 * preg_match rather than one per pattern. Merging is an optimisation, and the parser that decides
 * whether a pattern can take part refuses plenty of shapes - an unusual delimiter, flags it cannot
 * read, a body carrying the byte used to join them. Every refusal falls back to matching that
 * pattern on its own.
 *
 * That fallback is the thing worth testing, and none of it was: a masker that quietly dropped a
 * pattern it could not merge would leak exactly the values the pattern was written to hide, and
 * would do it silently. So each case below asserts masking still happens, not merely that nothing
 * threw.
 */
final class MaskerPatternParsingTest extends TestCase
{
    private const SECRET = ['password' => 'hunter2'];

    #[DataProvider('patternsThatCannotBeMerged')]
    public function testAPatternThatCannotBeMergedStillMasks(string $pattern): void
    {
        $masker = new SensitiveDataMasker([$pattern], '***', new NullLogger());

        self::assertSame(['password' => '***'], $masker->mask(self::SECRET), 'the fallback must match, not drop');
    }

    public static function patternsThatCannotBeMerged(): array
    {
        return [
            'brace delimiters' => ['{password}i'],
            'bracket delimiters' => ['[password]i'],
            'angle delimiters' => ['<password>i'],
            'parenthesis delimiters' => ['(password)i'],
            'hash delimiter' => ['#password#i'],
            'the join byte inside the body' => ["~pass\x01word|password~i"],
        ];
    }

    #[DataProvider('patternsThatAreNotPatterns')]
    public function testAValueThatIsNoPatternAtAllIsIgnoredWithoutAffectingTheRest(mixed $pattern): void
    {
        $masker = new SensitiveDataMasker([$pattern, '~password~i'], '***', new NullLogger());

        self::assertSame(['password' => '***'], $masker->mask(self::SECRET), 'one bad entry must not disable the list');
    }

    public static function patternsThatAreNotPatterns(): array
    {
        return [
            'empty string' => [''],
            'alphanumeric delimiter' => ['apassworda'],
            'backslash delimiter' => ['\\password\\'],
            'the join byte as delimiter' => ["\x01password\x01"],
            'no closing delimiter' => ['~password'],
            'unreadable flags' => ['~password~i!'],
            'not a string at all' => [42],
        ];
    }

    /**
     * Two patterns that each compile on their own but cannot compile together - duplicate group
     * names collide in a single expression. The merged form is rejected wholesale and every member
     * of that flag group goes back to being matched individually.
     */
    public function testAGroupThatFailsToCompileTogetherFallsBackToEachPatternAlone(): void
    {
        $masker = new SensitiveDataMasker(
            ['~(?P<dup>password)~i', '~(?P<dup>secret)~i'],
            '***',
            new NullLogger(),
        );

        self::assertSame(
            ['password' => '***', 'secret' => '***', 'other' => 'kept'],
            $masker->mask(['password' => 'hunter2', 'secret' => 's', 'other' => 'kept']),
        );
    }

    /**
     * Flags are normalised before grouping, so the same set written in another order is one group
     * rather than two - and the grouping must not change which keys match.
     */
    public function testFlagOrderDoesNotChangeWhatMatches(): void
    {
        $masker = new SensitiveDataMasker(['~password~iu', '~secret~ui'], '***', new NullLogger());

        self::assertSame(
            ['password' => '***', 'secret' => '***'],
            $masker->mask(['password' => 'p', 'secret' => 's']),
        );
    }

    /**
     * Case sensitivity belongs to the pattern, and merging must not lend one pattern's flags to
     * another: /secret/ without the i flag has no business matching SECRET because a neighbour
     * carried it.
     */
    public function testMergingDoesNotLendFlagsBetweenPatterns(): void
    {
        $masker = new SensitiveDataMasker(['~password~i', '~secret~'], '***', new NullLogger());

        $masked = $masker->mask(['PASSWORD' => 'p', 'SECRET' => 's', 'secret' => 's']);

        self::assertSame('***', $masked['PASSWORD'], 'the i flag is this pattern\'s own');
        self::assertSame('s', $masked['SECRET'], 'and it must not reach the one without it');
        self::assertSame('***', $masked['secret']);
    }
}
