<?php

declare(strict_types=1);

namespace Ftfy\Tests;

use Ftfy\Ftfy;
use Ftfy\TextFixerConfig;
use PHPUnit\Framework\TestCase;

/**
 * Tests driven by broken.txt.
 *
 * broken.txt is a JSON document containing clean UTF-8 text with HTML tags
 * inside the "answer" field.  It has no encoding errors, so:
 *
 *   - needsFix() must return false (no-fix needed)
 *   - fixText()  must return the content unchanged (idempotent)
 *
 * A second group of tests injects real encoding problems into the file's
 * content and asserts that both needsFix() and fixText() respond correctly,
 * verifying consistency between the two functions.
 */
class BrokenTxtTest extends TestCase
{
    private static string $raw = '';

    public static function setUpBeforeClass(): void
    {
        $path = dirname(__DIR__) . '/broken.txt';
        self::assertFileExists($path, 'broken.txt must exist in the project root');
        $content = file_get_contents($path);
        self::assertNotFalse($content, 'Could not read broken.txt');
        self::$raw = $content;
    }

    // -------------------------------------------------------------------------
    // Clean content: needsFix=false, fixText=no-op
    // -------------------------------------------------------------------------

    public function testNeedsFixFalseForCleanFile(): void
    {
        $this->assertFalse(
            Ftfy::needsFix(self::$raw),
            'needsFix() must return false — broken.txt contains no encoding errors'
        );
    }

    public function testFixTextIsNoOpForCleanFile(): void
    {
        $fixed = Ftfy::fixText(self::$raw);
        $this->assertSame(
            self::$raw,
            $fixed,
            'fixText() must leave clean broken.txt content unchanged'
        );
    }

    public function testNeedsFixAgreesWithFixTextForCleanFile(): void
    {
        $changed = Ftfy::fixText(self::$raw) !== self::$raw;
        $this->assertSame(
            $changed,
            Ftfy::needsFix(self::$raw),
            'needsFix() must agree with fixText() on clean content'
        );
    }

    // -------------------------------------------------------------------------
    // Inject mojibake into the file content and verify both functions fire
    // -------------------------------------------------------------------------

    /**
     * Builds broken variants of broken.txt content by injecting various
     * encoding problems and returns them as a data-provider array.
     *
     * @return iterable<string, array{string, string}>
     *   Keys describe the problem; values are [brokenText, description].
     */
    public static function brokenVariantsProvider(): iterable
    {
        $base = file_get_contents(dirname(__DIR__) . '/broken.txt');

        // 1. Mojibake: inject "sÃ³" (só mis-decoded as latin-1)
        yield 'mojibake injection' => [
            str_replace('WORKSPACE', "s\xC3\x83\xC2\xB3-WORKSPACE", $base),
            'mojibake sÃ³ → só',
        ];

        // 2. Curly quotes
        yield 'curly quotes' => [
            str_replace('"question"', "\u{201C}question\u{201D}", $base),
            'U+201C/U+201D curly double quotes',
        ];

        // 3. Latin ligature (fi → ﬁ)
        yield 'latin ligature' => [
            str_replace('information', "in\u{FB01}rmation", $base),
            'U+FB01 ﬁ ligature',
        ];

        // 4. Fullwidth characters
        yield 'fullwidth ascii' => [
            str_replace('WORKSPACE', "\u{FF37}\u{FF2F}\u{FF32}\u{FF2B}\u{FF33}\u{FF30}\u{FF21}\u{FF23}\u{FF25}", $base),
            'fullwidth ASCII ＷＯＲＫＳＰＡＣＥ',
        ];

        // 5. CRLF line endings
        yield 'crlf line endings' => [
            str_replace("\n", "\r\n", $base),
            'CRLF line endings',
        ];

        // 6. Terminal escape codes
        yield 'terminal escapes' => [
            "\033[1m" . $base . "\033[0m",
            'ANSI escape codes wrapping content',
        ];

        // 7. C1 control character (U+0080)
        yield 'c1 control char' => [
            str_replace('How can', "How\u{0080}can", $base),
            'U+0080 C1 control character',
        ];

        // 8. HTML entities when unescapeHtml is forced on a tag-free line
        yield 'html entity &amp;' => [
            'Clone &amp; share the form from your workspace.',
            'standalone &amp; entity (no HTML tags)',
        ];

        // 9. CESU-8 surrogate pair (💩 encoded as CESU-8)
        yield 'cesu8 surrogate' => [
            str_replace('Clone', "Clone\xED\xA0\xBD\xED\xB2\xA9", $base),
            'CESU-8 encoded surrogate pair',
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('brokenVariantsProvider')]
    public function testNeedsFixDetectsBrokenVariant(string $broken, string $desc): void
    {
        $this->assertTrue(
            Ftfy::needsFix($broken),
            "needsFix() must return true for: $desc"
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('brokenVariantsProvider')]
    public function testFixTextRepairsBrokenVariant(string $broken, string $desc): void
    {
        $fixed = Ftfy::fixText($broken);
        $this->assertNotSame(
            $broken,
            $fixed,
            "fixText() must change the text for: $desc"
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('brokenVariantsProvider')]
    public function testNeedsFixAgreesWithFixTextForBrokenVariant(string $broken, string $desc): void
    {
        $changed = Ftfy::fixText($broken) !== $broken;
        $this->assertSame(
            $changed,
            Ftfy::needsFix($broken),
            "needsFix() must agree with fixText() for: $desc"
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('brokenVariantsProvider')]
    public function testFixTextIsIdempotentForBrokenVariant(string $broken, string $desc): void
    {
        $fixed      = Ftfy::fixText($broken);
        $fixedAgain = Ftfy::fixText($fixed);
        $this->assertSame(
            $fixed,
            $fixedAgain,
            "fixText() must be idempotent for: $desc"
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('brokenVariantsProvider')]
    public function testNeedsFixFalseAfterFixForBrokenVariant(string $broken, string $desc): void
    {
        $fixed = Ftfy::fixText($broken);
        $this->assertFalse(
            Ftfy::needsFix($fixed),
            "needsFix() must return false after fixText() for: $desc"
        );
    }

    // -------------------------------------------------------------------------
    // Per-line consistency on the raw file
    // -------------------------------------------------------------------------

    /** @return iterable<string, array{int, string}> */
    public static function brokenTxtLinesProvider(): iterable
    {
        $path = dirname(__DIR__) . '/broken.txt';
        if (!file_exists($path)) {
            return;
        }
        foreach (explode("\n", file_get_contents($path)) as $i => $line) {
            if ($line === '') {
                continue;
            }
            yield 'line ' . ($i + 1) => [$i + 1, $line];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('brokenTxtLinesProvider')]
    public function testEachLineNeedsFixAgreesWithFixText(int $lineNo, string $line): void
    {
        $changed = Ftfy::fixText($line) !== $line;
        $flagged = Ftfy::needsFix($line);

        $this->assertSame(
            $changed,
            $flagged,
            sprintf(
                'needsFix() disagrees with fixText() on line %d: ' .
                'needsFix=%s, changed=%s, input=%s',
                $lineNo,
                $flagged ? 'true' : 'false',
                $changed ? 'true' : 'false',
                json_encode(mb_substr($line, 0, 80))
            )
        );
    }
}
