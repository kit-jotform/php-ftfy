<?php

declare(strict_types=1);

namespace Ftfy;

/**
 * Mojibake heuristic: detects unlikely character sequences that signal
 * mis-decoded text. Port of ftfy/badness.py.
 *
 * The BADNESS_RE pattern matches pairs/triples of characters from known
 * mojibake categories. A non-zero badness count means the text is probably
 * mojibake.
 */
final class Badness
{
    // -------------------------------------------------------------------------
    // Character category definitions
    //
    // Each constant is a PCRE character-class fragment (no surrounding []).
    // They are composed into BADNESS_RE below.
    // -------------------------------------------------------------------------

    private const C1        = '\x{0080}-\x{009F}';
    private const BAD       =
        '\x{00A6}'   // BROKEN BAR
        . '\x{00A4}' // CURRENCY SIGN
        . '\x{00A8}' // DIAERESIS
        . '\x{00AC}' // NOT SIGN
        . '\x{00AF}' // MACRON
        . '\x{00B8}' // CEDILLA
        . '\x{0192}' // LATIN SMALL LETTER F WITH HOOK
        . '\x{02C6}' // MODIFIER LETTER CIRCUMFLEX ACCENT
        . '\x{02C7}' // CARON
        . '\x{02D8}' // BREVE
        . '\x{02DB}' // OGONEK
        . '\x{02DC}' // SMALL TILDE
        . '\x{2020}' // DAGGER
        . '\x{2021}' // DOUBLE DAGGER
        . '\x{2030}' // PER MILLE SIGN
        . '\x{2310}' // REVERSED NOT SIGN
        . '\x{25CA}' // LOZENGE
        . '\x{FFFD}' // REPLACEMENT CHARACTER
        . '\x{00AA}' // FEMININE ORDINAL INDICATOR
        . '\x{00BA}'; // MASCULINE ORDINAL INDICATOR

    private const COMMON    =
        '\x{00A0}' // NO-BREAK SPACE
        . '\x{00AD}' // SOFT HYPHEN
        . '\x{00B7}' // MIDDLE DOT
        . '\x{00B4}' // ACUTE ACCENT
        . '\x{2013}' // EN DASH
        . '\x{2014}' // EM DASH
        . '\x{2015}' // HORIZONTAL BAR
        . '\x{2026}' // HORIZONTAL ELLIPSIS
        . '\x{2019}'; // RIGHT SINGLE QUOTATION MARK

    private const LAW       = '\x{00B6}\x{00A7}'; // PILCROW, SECTION SIGN
    private const CURRENCY  = '\x{00A2}\x{00A3}\x{00A5}\x{20A7}\x{20AC}';
    private const START_PUNCT =
        '\x{00A1}' // INVERTED EXCLAMATION MARK
        . '\x{00AB}' // LEFT-POINTING DOUBLE ANGLE QUOTATION MARK
        . '\x{00BF}' // INVERTED QUESTION MARK
        . '\x{00A9}' // COPYRIGHT SIGN
        . '\x{0384}' // GREEK TONOS
        . '\x{0385}' // GREEK DIALYTIKA TONOS
        . '\x{2018}' // LEFT SINGLE QUOTATION MARK
        . '\x{201A}' // SINGLE LOW-9 QUOTATION MARK
        . '\x{201C}' // LEFT DOUBLE QUOTATION MARK
        . '\x{201E}' // DOUBLE LOW-9 QUOTATION MARK
        . '\x{2022}' // BULLET
        . '\x{2039}' // SINGLE LEFT-POINTING ANGLE QUOTATION MARK
        . '\x{F8FF}'; // OS-specific (Apple logo)

    private const END_PUNCT =
        '\x{00AE}' // REGISTERED SIGN
        . '\x{00BB}' // RIGHT-POINTING DOUBLE ANGLE QUOTATION MARK
        . '\x{02DD}' // DOUBLE ACUTE ACCENT
        . '\x{201D}' // RIGHT DOUBLE QUOTATION MARK
        . '\x{203A}' // SINGLE RIGHT-POINTING ANGLE QUOTATION MARK
        . '\x{2122}'; // TRADE MARK SIGN

    private const NUMERIC   =
        '\x{00B2}\x{00B3}\x{00B9}' // superscripts
        . '\x{00B1}' // PLUS-MINUS
        . '\x{00BC}-\x{00BE}' // vulgar fractions
        . '\x{00D7}' // MULTIPLICATION SIGN
        . '\x{00B5}' // MICRO SIGN
        . '\x{00F7}' // DIVISION SIGN
        . '\x{2044}' // FRACTION SLASH
        . '\x{2202}\x{2206}\x{220F}\x{2211}' // math operators
        . '\x{221A}\x{221E}\x{2229}\x{222B}' // more math
        . '\x{2248}\x{2260}\x{2261}\x{2264}\x{2265}' // comparison
        . '\x{2116}'; // NUMERO SIGN

    private const KAOMOJI   =
        '\x{00D2}-\x{00D6}'
        . '\x{00D9}-\x{00DC}'
        . '\x{00F2}-\x{00F6}'
        . '\x{00F8}-\x{00FC}'
        . '\x{0150}' // O with double acute
        . '\x{014C}' // O with macron
        . '\x{016A}' // U with macron
        . '\x{0172}' // U with ogonek
        . '\x{00B0}'; // DEGREE SIGN

    private const UPPER_ACCENTED =
        '\x{00C0}-\x{00D1}'
        . '\x{00D8}'  // O WITH STROKE
        . '\x{00DC}'  // U WITH DIAERESIS
        . '\x{00DD}'  // Y WITH ACUTE
        . '\x{0102}\x{0100}\x{0104}\x{0106}\x{010C}\x{010E}\x{0110}'
        . '\x{0118}\x{011A}\x{011E}\x{0122}\x{012A}\x{0130}\x{0136}'
        . '\x{0139}\x{013B}\x{013D}\x{0141}\x{0143}\x{0145}\x{0147}'
        . '\x{0152}\x{0154}\x{015A}\x{015E}\x{0160}\x{0164}\x{0166}'
        . '\x{016E}\x{0170}\x{0178}\x{0179}\x{017B}\x{017D}'
        . '\x{0490}'; // CYRILLIC GHE WITH UPTURN

    private const LOWER_ACCENTED =
        '\x{00DF}'    // SHARP S
        . '\x{00E0}-\x{00F1}'
        . '\x{0103}\x{0101}\x{0105}\x{0107}\x{010D}\x{010F}\x{0111}'
        . '\x{0119}\x{011B}\x{011F}\x{0123}\x{012B}\x{0131}\x{0137}'
        . '\x{013A}\x{013C}\x{013E}\x{0142}\x{0144}\x{0146}\x{0148}'
        . '\x{0153}\x{0155}\x{015B}\x{015F}\x{0161}\x{0165}\x{0167}'
        . '\x{016F}\x{0171}\x{017A}\x{017C}\x{017E}'
        . '\x{0491}'  // cyrillic ghe with upturn (small)
        . '\x{FB01}\x{FB02}'; // fi fl ligatures

    private const UPPER_COMMON =
        '\x{00DE}'    // LATIN CAPITAL LETTER THORN
        . '\x{0391}-\x{03A9}'  // Greek capital
        . '\x{0386}\x{0388}\x{0389}\x{038A}\x{038C}\x{038E}\x{038F}'
        . '\x{03AA}\x{03AB}'
        . '\x{0400}-\x{042F}'; // Cyrillic capital (U+0400-U+042F)

    private const LOWER_COMMON =
        '\x{03B1}-\x{03C9}'  // Greek small
        . '\x{03AC}\x{03AD}\x{03AE}\x{03AF}\x{03B0}'
        . '\x{0430}-\x{045F}'; // Cyrillic small

    private const BOX =
        '\x{2502}\x{250C}\x{2510}\x{2518}\x{251C}\x{2524}\x{252C}\x{253C}'
        . '\x{2550}-\x{256C}'
        . '\x{2580}\x{2584}\x{2588}\x{258C}\x{2590}-\x{2593}';

    private static ?string $pattern = null;

    public static function getPattern(): string
    {
        if (self::$pattern !== null) {
            return self::$pattern;
        }

        $c1 = self::C1;
        $bad = self::BAD;
        $common = self::COMMON;
        $law = self::LAW;
        $currency = self::CURRENCY;
        $sp = self::START_PUNCT;
        $ep = self::END_PUNCT;
        $num = self::NUMERIC;
        $kao = self::KAOMOJI;
        $ua = self::UPPER_ACCENTED;
        $la = self::LOWER_ACCENTED;
        $uc = self::UPPER_COMMON;
        $lc = self::LOWER_COMMON;
        $box = self::BOX;

        self::$pattern = '/
            [' . $c1 . ']
            |
            [' . $bad . $la . $ua . $box . $sp . $ep . $currency . $num . $law . '][' . $bad . ']
            |
            [a-zA-Z][' . $lc . $uc . '][' . $bad . ']
            |
            [' . $bad . '][' . $la . $ua . $box . $sp . $ep . $currency . $num . $law . ']
            |
            [' . $la . $lc . $box . $ep . $currency . $num . '][' . $ua . ']
            |
            [' . $box . $ep . $currency . $num . '][' . $la . ']
            |
            [' . $la . $box . $ep . '][' . $currency . ']
            |
            \s[' . $ua . '][' . $currency . ']
            |
            [' . $ua . $box . '][' . $num . $law . ']
            |
            [' . $la . $ua . $box . $currency . $ep . '][' . $sp . '][' . $num . ']
            |
            [' . $la . $ua . $currency . $num . $box . $law . '][' . $ep . '][' . $sp . ']
            |
            [' . $currency . $num . $box . '][' . $sp . ']
            |
            [a-z][' . $ua . '][' . $sp . $currency . ']
            |
            [' . $box . '][' . $kao . ']
            |
            [' . $la . $ua . $currency . $num . $sp . $ep . $law . '][' . $box . ']
            |
            [' . $box . '][' . $ep . ']
            |
            [' . $la . $ua . '][' . $sp . $ep . ']\w
            |
            [\x{0152}\x{0153}][^A-Za-z]
            |
            [' . $ua . ']\x{00B0}
            |
            [\x{00C2}\x{00C3}\x{00CE}\x{00D0}][\x{20AC}\x{0153}\x{0160}\x{0161}\x{00A2}\x{00A3}\x{0178}\x{017E}\x{00A0}\x{00AD}\x{00AE}\x{00A9}\x{00B0}\x{00B7}\x{00BB}' . $sp . $ep . '\x{2013}\x{2014}\x{00B4}]
            |
            \x{00D7}[\x{00B2}\x{00B3}]
            |
            [\x{00D8}\x{00D9}][' . $common . $currency . $bad . $num . $sp . '\x{0178}\x{0160}\x{00AE}\x{00B0}\x{00B5}\x{00BB}]
            [\x{00D8}\x{00D9}][' . $common . $currency . $bad . $num . $sp . '\x{0178}\x{0160}\x{00AE}\x{00B0}\x{00B5}\x{00BB}]
            |
            \x{00E0}[\x{00B2}\x{00B5}\x{00B9}\x{00BC}\x{00BD}\x{00BE}]
            |
            \x{221A}[\x{00B1}\x{2202}\x{2020}\x{2260}\x{00AE}\x{2122}\x{00B4}\x{2264}\x{2265}\x{00A5}\x{00B5}\x{00F8}]
            |
            \x{2248}[\x{00B0}\x{00A2}]
            |
            \x{201A}\x{00C4}[\x{00EC}\x{00EE}\x{00EF}\x{00F2}\x{00F4}\x{00FA}\x{00F9}\x{00FB}\x{2020}\x{00B0}\x{00A2}\x{03C0}]
            |
            \x{201A}[\x{00E2}\x{00F3}][\x{00E0}\x{00E4}\x{00B0}\x{00EA}]
            |
            \x{0432}\x{0402}
            |
            [\x{0412}\x{0413}\x{0420}\x{0421}][' . $c1 . $bad . $sp . $ep . $currency . '\x{00B0}\x{00B5}][\x{0412}\x{0413}\x{0420}\x{0421}]
            |
            \x{0413}\x{044A}\x{0412}\x{0415}\x{0412}.[A-Za-z ]
            |
            \x{00C3}[\x{00A0}\x{00A1}]
            |
            [a-z]\s?[\x{00C3}\x{00C2}][ ]
            |
            ^[\x{00C3}\x{00C2}][ ]
            |
            [a-z.,?!\x{00AE}\x{2019}\x{203A}\x{2122}]\x{00C2}[ ' . $sp . $ep . ']
            |
            \x{03B2}\x{20AC}[\x{2122}\x{00A0}\x{0386}\x{00AD}\x{00AE}\x{00B0}]
            |
            [\x{0392}\x{0393}\x{039E}\x{039F}][' . $c1 . $bad . $sp . $ep . $currency . '\x{00B0}][\x{0392}\x{0393}\x{039E}\x{039F}]
            |
            \x{0101}\x{20AC}
        /ux';

        return self::$pattern;
    }

    /**
     * Count the number of badness matches in the text.
     */
    public static function badness(string $text): int
    {
        $count = preg_match_all(self::getPattern(), $text);
        return $count === false ? 0 : $count;
    }

    /**
     * Return true if the text looks like it contains mojibake.
     */
    public static function isBad(string $text): bool
    {
        return (bool) preg_match(self::getPattern(), $text);
    }
}
