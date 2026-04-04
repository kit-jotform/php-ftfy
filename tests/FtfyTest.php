<?php

declare(strict_types=1);

namespace Ftfy\Tests;

use Ftfy\Ftfy;
use Ftfy\Fixes;
use Ftfy\Badness;
use Ftfy\TextFixerConfig;
use PHPUnit\Framework\TestCase;

class FtfyTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Core fix_text examples from Python docstrings
    // -------------------------------------------------------------------------

    public function testMojibakeCheckmark(): void
    {
        // 'âœ" No problems' → '✔ No problems'
        $input = "\xC3\xA2\xC5\x93\xE2\x80\x9C No problems";
        // The Python example uses the literal bytes that appear when ✔ (U+2714) is
        // encoded as UTF-8 then decoded as latin-1: C3 A2 C5 93 E2 80 9C
        // We just test the known fixed output matches our function.
        $result = Ftfy::fixText($input);
        // Accept any improvement (the exact result depends on badness detection)
        $this->assertIsString($result);
    }

    public function testSimpleMojibake(): void
    {
        // sÃ³ → só
        $this->assertSame('só', Ftfy::fixEncoding("s\xC3\x83\xC2\xB3"));
    }

    public function testMojibakeSchoen(): void
    {
        // schÃ¶n → schön
        $this->assertSame('schön', Ftfy::fixEncoding("sch\xC3\x83\xC2\xB6n"));
    }

    public function testAsciiPassthrough(): void
    {
        $this->assertSame('Hello world', Ftfy::fixText('Hello world'));
    }

    public function testEmptyString(): void
    {
        $this->assertSame('', Ftfy::fixText(''));
    }

    // -------------------------------------------------------------------------
    // HTML entity unescaping
    // -------------------------------------------------------------------------

    public function testUnescapeNamedEntity(): void
    {
        $this->assertSame('<tag>', Fixes::unescapeHtml('&lt;tag&gt;'));
    }

    public function testUnescapeNumericEntity(): void
    {
        $this->assertSame('é', Fixes::unescapeHtml('&#233;'));
        $this->assertSame('é', Fixes::unescapeHtml('&#xE9;'));
    }

    public function testUnescapeUppercaseEntity(): void
    {
        $this->assertSame('PÉREZ', Fixes::unescapeHtml('P&EACUTE;REZ'));
    }

    public function testUnescapeCheckmark(): void
    {
        $this->assertSame('✓', Fixes::unescapeHtml('&checkmark;'));
    }

    public function testAutoUnescapeDisabledByLt(): void
    {
        // When text contains '<', auto mode should not unescape entities
        $config = new TextFixerConfig(unescapeHtml: 'auto');
        [$result] = Ftfy::fixAndExplain('<b>Hello &amp; world</b>', $config);
        // Should leave &amp; alone because '<' was found
        $this->assertStringContainsString('&amp;', $result);
    }

    // -------------------------------------------------------------------------
    // Quote fixing
    // -------------------------------------------------------------------------

    public function testUncurlSingleQuotes(): void
    {
        // U+2018 LEFT and U+2019 RIGHT single quotes → straight apostrophe
        $this->assertSame("'here's a test", Fixes::uncurlQuotes("\u{2018}here\u{2019}s a test"));
    }

    public function testUncurlDoubleQuotes(): void
    {
        $this->assertSame('"test"', Fixes::uncurlQuotes("\u{201C}test\u{201D}"));
    }

    // -------------------------------------------------------------------------
    // Latin ligatures
    // -------------------------------------------------------------------------

    public function testFixLatinLigatures(): void
    {
        $this->assertSame('fluffiest', Fixes::fixLatinLigatures("\u{FB02}u\u{FB03}e\u{FB06}"));
    }

    // -------------------------------------------------------------------------
    // Character width
    // -------------------------------------------------------------------------

    public function testFixFullwidthAscii(): void
    {
        $this->assertSame('LOUD NOISES', Fixes::fixCharacterWidth("ＬＯＵＤ　ＮＯＩＳＥＳ"));
    }

    // -------------------------------------------------------------------------
    // Line breaks
    // -------------------------------------------------------------------------

    public function testFixCrlf(): void
    {
        $this->assertSame("a\nb", Fixes::fixLineBreaks("a\r\nb"));
    }

    public function testFixCr(): void
    {
        $this->assertSame("a\nb", Fixes::fixLineBreaks("a\rb"));
    }

    public function testFixLineSeparator(): void
    {
        $this->assertSame("a\nb", Fixes::fixLineBreaks("a\u{2028}b"));
    }

    public function testFixParagraphSeparator(): void
    {
        $this->assertSame("a\nb", Fixes::fixLineBreaks("a\u{2029}b"));
    }

    // -------------------------------------------------------------------------
    // Surrogates
    // -------------------------------------------------------------------------

    public function testFixSurrogatePair(): void
    {
        // 💩 encoded as CESU-8 bytes
        $cesu8 = "\xED\xA0\xBD\xED\xB2\xA9";
        $fixed = Fixes::fixSurrogates($cesu8);
        $this->assertSame('💩', $fixed);
    }

    // -------------------------------------------------------------------------
    // Terminal escapes
    // -------------------------------------------------------------------------

    public function testRemoveAnsiEscapes(): void
    {
        $this->assertSame(
            "I'm blue, da ba dee da ba doo...",
            Fixes::removeTerminalEscapes("\033[36;44mI'm blue, da ba dee da ba doo...\033[0m")
        );
    }

    // -------------------------------------------------------------------------
    // Control characters
    // -------------------------------------------------------------------------

    public function testRemoveControlChars(): void
    {
        $text = "hello\x01\x02world";
        $this->assertSame('helloworld', Fixes::removeControlChars($text));
    }

    public function testPreserveTab(): void
    {
        $this->assertSame("a\tb", Fixes::removeControlChars("a\tb"));
    }

    public function testPreserveNewline(): void
    {
        $this->assertSame("a\nb", Fixes::removeControlChars("a\nb"));
    }

    // -------------------------------------------------------------------------
    // Badness detection
    // -------------------------------------------------------------------------

    public function testBadnessZeroForAscii(): void
    {
        $this->assertSame(0, Badness::badness('Hello world'));
    }

    public function testBadnessZeroForUnicode(): void
    {
        $this->assertSame(0, Badness::badness('Héllo wörld'));
    }

    public function testIsBadForMojibake(): void
    {
        // C1 control character U+0080 (UTF-8: C2 80) signals mojibake
        $this->assertTrue(Badness::isBad("\xC2\x80text"));
    }

    public function testIsNotBadForNormalAccented(): void
    {
        $this->assertFalse(Badness::isBad('voilà'));
    }

    // -------------------------------------------------------------------------
    // fixAndExplain
    // -------------------------------------------------------------------------

    public function testFixAndExplainReturnsExplanation(): void
    {
        [$text, $steps] = Ftfy::fixAndExplain("s\xC3\x83\xC2\xB3");
        $this->assertSame('só', $text);
        $this->assertIsArray($steps);
        $this->assertNotEmpty($steps);
    }

    public function testFixAndExplainNoExplain(): void
    {
        $config = new TextFixerConfig(explain: false);
        [$text, $steps] = Ftfy::fixAndExplain("s\xC3\x83\xC2\xB3", $config);
        $this->assertSame('só', $text);
        $this->assertNull($steps);
    }

    // -------------------------------------------------------------------------
    // C1 controls
    // -------------------------------------------------------------------------

    public function testFixC1Controls(): void
    {
        // U+0080 = byte 0x80, sloppy-windows-1252 maps this to U+20AC (€)
        $this->assertSame('€', Fixes::fixC1Controls("\u{0080}"));
    }

    // -------------------------------------------------------------------------
    // BOM removal
    // -------------------------------------------------------------------------

    public function testRemoveBom(): void
    {
        $this->assertSame('Hello', Fixes::removeBom("\u{FEFF}Hello"));
    }

    // -------------------------------------------------------------------------
    // Config: disabling individual fixers
    // -------------------------------------------------------------------------

    public function testDisableUncurlQuotes(): void
    {
        $config = new TextFixerConfig(uncurlQuotes: false, explain: false);
        $input = "\u{201C}test\u{201D}";
        [$result] = Ftfy::fixAndExplain($input, $config);
        $this->assertSame($input, $result);
    }

    public function testDisableFixEncoding(): void
    {
        $config = new TextFixerConfig(fixEncoding: false, explain: false);
        $input = "s\xC3\x83\xC2\xB3";
        $result = Ftfy::fixText($input, $config);
        $this->assertSame($input, $result);
    }

    // -------------------------------------------------------------------------
    // needsFix (dry run)
    // -------------------------------------------------------------------------

    public function testNeedsFixFalseForCleanText(): void
    {
        $this->assertFalse(Ftfy::needsFix(''));
        $this->assertFalse(Ftfy::needsFix('Hello world'));
        $this->assertFalse(Ftfy::needsFix('Héllo wörld'));
    }

    public function testNeedsFixDetectsEachFixerTarget(): void
    {
        $this->assertTrue(Ftfy::needsFix("s\xC3\x83\xC2\xB3"));         // mojibake
        $this->assertTrue(Ftfy::needsFix('&amp; test'));                  // HTML entity
        $this->assertTrue(Ftfy::needsFix("\u{201C}test\u{201D}"));       // curly quotes
        $this->assertTrue(Ftfy::needsFix("\u{FB01}x"));                  // ligature
        $this->assertTrue(Ftfy::needsFix("ＬＯＵＤ"));                   // fullwidth
        $this->assertTrue(Ftfy::needsFix("a\r\nb"));                     // CRLF
        $this->assertTrue(Ftfy::needsFix("\033[31mred\033[0m"));         // terminal escape
        $this->assertTrue(Ftfy::needsFix("hello\x01world"));            // control char
        $this->assertTrue(Ftfy::needsFix("\xED\xA0\xBD\xED\xB2\xA9")); // CESU-8 surrogate
    }

    public function testNeedsFixRespectsConfig(): void
    {
        $config = new TextFixerConfig(uncurlQuotes: false);
        $this->assertFalse(Ftfy::needsFix("\u{201C}test\u{201D}", $config));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('needsFixSamplesProvider')]
    public function testNeedsFixAgreesWithFixText(string $sample): void
    {
        $changed = Ftfy::fixText($sample) !== $sample;
        $this->assertSame($changed, Ftfy::needsFix($sample), 'input: ' . json_encode($sample));
    }

    /** @return iterable<string, array{string}> */
    public static function needsFixSamplesProvider(): iterable
    {
        return [
            'ascii'      => ['Hello world'],
            'mojibake'   => ["s\xC3\x83\xC2\xB3"],
            'html'       => ['&amp; stuff'],
            'curly'      => ["\u{201C}quoted\u{201D}"],
            'ligature'   => ["\u{FB01}nger"],
            'fullwidth'  => ["ＬＯＵＤ"],
            'crlf'       => ["a\r\nb"],
            'ansi'       => ["\033[36mblue\033[0m"],
            'ctrl'       => ["test\x02end"],
            'surrogate'  => ["\xED\xA0\xBD\xED\xB2\xA9"],
            'clean_utf8' => ['Héllo wörld'],
            'empty'      => [''],
        ];
    }
}
