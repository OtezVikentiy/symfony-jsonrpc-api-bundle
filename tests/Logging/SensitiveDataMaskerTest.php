<?php

namespace OV\JsonRPCAPIBundle\Tests\Logging;

use OV\JsonRPCAPIBundle\Core\Logging\SensitiveDataMasker;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use OV\JsonRPCAPIBundle\Tests\Fixtures\TestLogger;

final class SensitiveDataMaskerTest extends TestCase
{
    /**
     * An uploaded file reaches the logger inside params. Encoding it as it stands puts either file
     * content or a server-side temporary path into a log line, so it is replaced by the three things
     * worth recording about it.
     */
    public function testUploadedFileIsRecordedAsMetadata(): void
    {
        $masker = new SensitiveDataMasker(['~^password$~i'], '***', new NullLogger());

        $result = $masker->mask(['params' => ['file' => $this->uploadedFile()]]);

        self::assertSame(
            ['params' => ['file' => ['originalName' => 'report.pdf', 'size' => 9, 'mimeType' => 'application/pdf']]],
            $result,
        );
    }

    public function testUploadedFileIsDescribedEvenWithNoMaskingPatternsConfigured(): void
    {
        // Describing a file is serialization, not masking: switching key masking off must not put
        // the raw instance back into the log line.
        $masker = new SensitiveDataMasker([], '***', new NullLogger());

        $result = $masker->mask(['file' => $this->uploadedFile()]);

        self::assertSame(
            ['file' => ['originalName' => 'report.pdf', 'size' => 9, 'mimeType' => 'application/pdf']],
            $result,
        );
    }

    public function testASensitiveKeyStillWinsOverTheFileDescription(): void
    {
        // A file part named after a credential is masked like any other field of that name: the
        // placeholder says less than the metadata would, which is the direction to err in.
        $masker = new SensitiveDataMasker(['~signature~i'], '***', new NullLogger());

        $result = $masker->mask(['signature' => $this->uploadedFile()]);

        self::assertSame(['signature' => '***'], $result);
    }

    public function testAMovedUploadedFileIsDescribedWithoutASize(): void
    {
        // getSize() stats the temporary file, and the handler may already have moved it. Reporting
        // an unknown size beats raising a warning from inside the logger.
        $masker = new SensitiveDataMasker([], '***', new NullLogger());
        $file = $this->uploadedFile();
        unlink($file->getPathname());

        $result = $masker->mask(['file' => $file]);

        self::assertNull($result['file']['size']);
        self::assertSame('report.pdf', $result['file']['originalName']);
    }

    private function uploadedFile(): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'ov_masker_');
        file_put_contents($path, 'PDF-BYTES');
        $this->tempFiles[] = $path;

        return new UploadedFile($path, 'report.pdf', 'application/pdf', null, true);
    }

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $tempFile) {
            if (is_file($tempFile)) {
                unlink($tempFile);
            }
        }

        $this->tempFiles = [];
    }

    public function testMasksTopLevelKeyMatchingPattern(): void
    {
        $masker = new SensitiveDataMasker(['~^password$~i'], '***', new NullLogger());

        $result = $masker->mask(['user' => 'alice', 'password' => 'p4ss']);

        self::assertSame(['user' => 'alice', 'password' => '***'], $result);
    }

    public function testMasksNestedKeysRecursively(): void
    {
        $masker = new SensitiveDataMasker(['~^token$~i'], '***', new NullLogger());

        $result = $masker->mask([
            'params' => [
                'auth' => ['token' => 'abc', 'kind' => 'bearer'],
            ],
        ]);

        self::assertSame(
            ['params' => ['auth' => ['token' => '***', 'kind' => 'bearer']]],
            $result,
        );
    }

    public function testMasksArrayOfObjects(): void
    {
        $masker = new SensitiveDataMasker(['~^secret$~i'], '***', new NullLogger());

        $result = $masker->mask([
            'items' => [
                ['name' => 'a', 'secret' => 's1'],
                ['name' => 'b', 'secret' => 's2'],
            ],
        ]);

        self::assertSame(
            ['items' => [['name' => 'a', 'secret' => '***'], ['name' => 'b', 'secret' => '***']]],
            $result,
        );
    }

    public function testReplacesEntireSubtreeWhenKeyMatches(): void
    {
        $masker = new SensitiveDataMasker(['~^credentials$~i'], '***', new NullLogger());

        $result = $masker->mask([
            'credentials' => ['login' => 'u', 'password' => 'p', 'meta' => ['x' => 1]],
        ]);

        self::assertSame(['credentials' => '***'], $result);
    }

    public function testPassesThroughWhenNoPatternsConfigured(): void
    {
        $masker = new SensitiveDataMasker([], '***', new NullLogger());

        $input = ['a' => 1, 'b' => ['c' => 2]];
        self::assertSame($input, $masker->mask($input));
    }

    public function testHandlesEmptyArray(): void
    {
        $masker = new SensitiveDataMasker(['~^x$~'], '***', new NullLogger());

        self::assertSame([], $masker->mask([]));
    }

    public function testInvalidRegexIsSkippedAndWarned(): void
    {
        $logger = new TestLogger();
        $masker = new SensitiveDataMasker(['~^password$~i', 'invalid('], '***', $logger);

        $result = $masker->mask(['password' => 'x', 'other' => 'y']);

        self::assertSame(['password' => '***', 'other' => 'y'], $result);
        self::assertTrue($logger->hasWarningRecords());
        self::assertSame('invalid(', $logger->records[0]['context']['pattern']);
    }

    /**
     * `logging.masking.key_patterns` is declared with Symfony's scalarPrototype(), which accepts
     * any scalar - not just strings - so a misconfigured YAML value (e.g. `true` instead of a
     * quoted regex) reaches the constructor as-is. Before this test's fix, a non-string entry was
     * stored as though it were a valid pattern and only failed once matched against a real key,
     * where preg_match() under strict_types throws a TypeError instead of returning false - masking
     * broke on the very first logged request instead of degrading to "skip and warn" like every
     * other invalid pattern here.
     */
    public function testNonStringPatternIsSkippedAndWarnedInsteadOfCrashing(): void
    {
        $logger = new TestLogger();
        $masker = new SensitiveDataMasker(['~^password$~i', true], '***', $logger);

        $result = $masker->mask(['password' => 'x', 'other' => 'y']);

        self::assertSame(['password' => '***', 'other' => 'y'], $result);
        self::assertTrue($logger->hasWarningRecords());
    }

    public function testInvalidRegexWarnsOnlyOnce(): void
    {
        $logger = new TestLogger();
        $masker = new SensitiveDataMasker(['invalid('], '***', $logger);

        $masker->mask(['a' => 1]);
        $masker->mask(['b' => 2]);
        $masker->mask(['c' => 3]);

        $warningsCount = count(array_filter(
            $logger->records,
            static fn (array $r) => $r['level'] === 'warning',
        ));
        self::assertSame(1, $warningsCount);
    }

    public function testCustomPlaceholder(): void
    {
        $masker = new SensitiveDataMasker(['~^token$~'], '[REDACTED]', new NullLogger());

        self::assertSame(['token' => '[REDACTED]'], $masker->mask(['token' => 'x']));
    }

    public function testMergesMultiplePatternsWithTheSameFlags(): void
    {
        $masker = new SensitiveDataMasker(
            ['~^password$~i', '~^token$~i', '~^secret$~i'],
            '***',
            new NullLogger(),
        );

        $result = $masker->mask(['password' => 'a', 'TOKEN' => 'b', 'secret' => 'c', 'other' => 'd']);

        self::assertSame(
            ['password' => '***', 'TOKEN' => '***', 'secret' => '***', 'other' => 'd'],
            $result,
        );
    }

    public function testGroupsPatternsWithDifferentFlagsSeparately(): void
    {
        $masker = new SensitiveDataMasker(
            ['~^password$~i', '~^Token$~'],
            '***',
            new NullLogger(),
        );

        $caseInsensitiveMatch = $masker->mask(['PASSWORD' => 'a']);
        self::assertSame(['PASSWORD' => '***'], $caseInsensitiveMatch);

        $caseSensitiveNoMatch = $masker->mask(['token' => 'b']);
        self::assertSame(['token' => 'b'], $caseSensitiveNoMatch, 'the case-sensitive pattern "Token" must not match lowercase "token"');

        $caseSensitiveMatch = $masker->mask(['Token' => 'c']);
        self::assertSame(['Token' => '***'], $caseSensitiveMatch);
    }

    public function testValidPatternStillMatchesWhenMixedWithAnInvalidOne(): void
    {
        $logger = new TestLogger();
        $masker = new SensitiveDataMasker(
            ['~^password$~i', 'invalid(', '~^secret$~i'],
            '***',
            $logger,
        );

        $result = $masker->mask(['password' => 'a', 'secret' => 'b', 'other' => 'c']);

        self::assertSame(['password' => '***', 'secret' => '***', 'other' => 'c'], $result);
        self::assertTrue($logger->hasWarningRecords());
    }

    public function testAnchoredPatternsDoNotBleedAcrossMergedAlternatives(): void
    {
        $masker = new SensitiveDataMasker(
            ['~^password$~i', '~^token$~i'],
            '***',
            new NullLogger(),
        );

        $result = $masker->mask(['passwordtoken' => 'should not match either anchored pattern']);

        self::assertSame(['passwordtoken' => 'should not match either anchored pattern'], $result);
    }

    /**
     * Merging renumbers capturing groups, so a pattern referring back to one by number silently
     * starts matching something else. The damage shows up only in company: alone the pattern works,
     * and adding an unrelated group-bearing entry to the list is what stops it masking.
     */
    public function testBackreferencePatternKeepsMaskingWhenOtherGroupPatternsArePresent(): void
    {
        $alone = new SensitiveDataMasker(['~(x)y\1~i'], '***', new NullLogger());
        $inCompany = new SensitiveDataMasker(['~(a)b\1~i', '~(x)y\1~i'], '***', new NullLogger());

        self::assertSame(['xyx' => '***'], $alone->mask(['xyx' => 'secret']));
        self::assertSame(['xyx' => '***'], $inCompany->mask(['xyx' => 'secret']));
    }

    public function testGSyntaxBackreferenceSurvivesTheSameWay(): void
    {
        $masker = new SensitiveDataMasker(['~(a)b\1~i', '~(z)w\g{1}~i'], '***', new NullLogger());

        self::assertSame(['zwz' => '***', 'aba' => '***'], $masker->mask(['zwz' => 'secret', 'aba' => 'secret']));
    }

    public function testConditionalOnAGroupNumberIsNotMerged(): void
    {
        $masker = new SensitiveDataMasker(['~(a)b\1~i', '~(q)?r(?(1)s|t)~i'], '***', new NullLogger());

        self::assertSame(['qrs' => '***'], $masker->mask(['qrs' => 'secret']));
    }
}
