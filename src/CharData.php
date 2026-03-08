<?php

declare(strict_types=1);

namespace Ftfy;

/**
 * Character data: regex patterns, HTML entity tables, and character maps used
 * by the various fixers. This is a direct port of ftfy/chardata.py.
 */
final class CharData
{
    // Encodings tried (in order) when looking for mojibake fixes.
    public const CHARMAP_ENCODINGS = [
        'latin-1',
        'sloppy-windows-1252',
        'sloppy-windows-1251',
        'sloppy-windows-1250',
        'sloppy-windows-1253',
        'sloppy-windows-1254',
        'sloppy-windows-1257',
        'iso-8859-2',
        'macroman',
        'cp437',
    ];

    // -------------------------------------------------------------------------
    // Regex patterns
    // -------------------------------------------------------------------------

    /**
     * Matches single curly-quote characters that should become straight quotes.
     * U+02BC MODIFIER LETTER APOSTROPHE, U+2018-U+201B
     */
    public const SINGLE_QUOTE_RE = '/[\x{02BC}\x{2018}-\x{201B}]/u';

    /**
     * Matches double curly-quote characters. U+201C-U+201F
     */
    public const DOUBLE_QUOTE_RE = '/[\x{201C}-\x{201F}]/u';

    /**
     * Matches HTML entity references (numeric and named) ending in semicolons.
     */
    public const HTML_ENTITY_RE = '/&#?[0-9A-Za-z]{1,24};/u';

    /**
     * Matches C1 control characters U+0080–U+009F still present in a string.
     */
    public const C1_CONTROL_RE = '/[\x{0080}-\x{009F}]/u';

    /**
     * Matches UTF-8 sequences in a byte string where 0x20 (space) appears
     * where 0xA0 (no-break space) would complete a valid UTF-8 sequence.
     * Applied to raw binary strings (not UTF-8).
     */
    public const ALTERED_UTF8_RE =
        '/[\xC2\xC3\xC5\xCE\xD0\xD9][ ]'
        . '|[\xE2\xE3][ ][\x80-\x84\x86-\x9F\xA1-\xBF]'
        . '|[\xE0-\xE3][\x80-\x84\x86-\x9F\xA1-\xBF][ ]'
        . '|[\xF0][ ][\x80-\xBF][\x80-\xBF]'
        . '|[\xF0][\x80-\xBF][ ][\x80-\xBF]'
        . '|[\xF0][\x80-\xBF][\x80-\xBF][ ]'
        . '/';

    /**
     * Matches UTF-8 / CESU-8 sequences where continuation bytes have been
     * replaced by 0x1A (the SUBSTITUTE character used by sloppy codecs) or
     * by '?'. Applied to raw binary strings.
     */
    public const LOSSY_UTF8_RE =
        '/[\xC2-\xDF][\x1A]'
        . '|[\xC2-\xC3][?]'
        . '|\xED[\xA0-\xAF][\x1A?]\xED[\xB0-\xBF][\x1A?\x80-\xBF]'
        . '|\xED[\xA0-\xAF][\x1A?\x80-\xBF]\xED[\xB0-\xBF][\x1A?]'
        . '|[\xE0-\xEF][\x1A?][\x1A\x80-\xBF]'
        . '|[\xE0-\xEF][\x1A\x80-\xBF][\x1A?]'
        . '|[\xF0-\xF4][\x1A?][\x1A\x80-\xBF][\x1A\x80-\xBF]'
        . '|[\xF0-\xF4][\x1A\x80-\xBF][\x1A?][\x1A\x80-\xBF]'
        . '|[\xF0-\xF4][\x1A\x80-\xBF][\x1A\x80-\xBF][\x1A?]'
        . '|\x1A'
        . '/';

    // -------------------------------------------------------------------------
    // Character maps
    // -------------------------------------------------------------------------

    /**
     * Latin typographic ligatures and digraphs mapped to their component letters.
     * Keys are Unicode codepoints; values are replacement strings.
     *
     * @var array<int,string>
     */
    public const LIGATURES = [
        0x0132 => 'IJ',  // Ĳ Dutch
        0x0133 => 'ij',  // ĳ
        0x0149 => "\u{02BC}n",  // ŉ Afrikaans digraph
        0x01F1 => 'DZ',  0x01F2 => 'Dz',  0x01F3 => 'dz',
        0x01C4 => "D\u{017D}", 0x01C5 => "D\u{017E}", 0x01C6 => "d\u{017E}",
        0x01C7 => 'LJ',  0x01C8 => 'Lj',  0x01C9 => 'lj',
        0x01CA => 'NJ',  0x01CB => 'Nj',  0x01CC => 'nj',
        0xFB00 => 'ff',
        0xFB01 => 'fi',
        0xFB02 => 'fl',
        0xFB03 => 'ffi',
        0xFB04 => 'ffl',
        0xFB05 => "\u{017F}t",  // ﬅ long-s t
        0xFB06 => 'st',
    ];

    /**
     * Control characters that should be removed (mapped to null / removed).
     * These are codepoints we strip in remove_control_chars.
     *
     * @var int[]
     */
    public const CONTROL_CHAR_CODEPOINTS = [
        // U+0000–U+0008
        0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08,
        // U+000B
        0x0B,
        // U+000E–U+001F
        0x0E, 0x0F, 0x10, 0x11, 0x12, 0x13, 0x14, 0x15,
        0x16, 0x17, 0x18, 0x19, 0x1A, 0x1B, 0x1C, 0x1D, 0x1E, 0x1F,
        // U+007F DEL
        0x7F,
        // U+206A–U+206F Deprecated Arabic formatting
        0x206A, 0x206B, 0x206C, 0x206D, 0x206E, 0x206F,
        // U+FEFF BOM
        0xFEFF,
        // U+FFF9–U+FFFB Interlinear annotation
        0xFFF9, 0xFFFA, 0xFFFB,
        // U+FFFC Object Replacement Character
        0xFFFC,
    ];

    // -------------------------------------------------------------------------
    // HTML entities
    // -------------------------------------------------------------------------

    /** @var array<string,string>|null */
    private static ?array $htmlEntities = null;

    /**
     * Return the HTML entity → character map used by unescape_html.
     * Includes a limited set of uppercase-name entities (e.g. &EACUTE;).
     *
     * @return array<string,string>
     */
    public static function getHtmlEntities(): array
    {
        if (self::$htmlEntities !== null) {
            return self::$htmlEntities;
        }

        self::$htmlEntities = self::buildHtmlEntities();
        return self::$htmlEntities;
    }

    private static function buildHtmlEntities(): array
    {
        // PHP's html_entity_decode / get_html_translation_table doesn't expose
        // the full HTML5 entity list, so we embed the commonly needed ones
        // and supplement with PHP's built-in table.
        $entities = [];

        // Pull from PHP's HTML5 translation table (a subset of html5 entities).
        $table = get_html_translation_table(HTML_ENTITIES, ENT_HTML5 | ENT_QUOTES, 'UTF-8');
        foreach ($table as $char => $entity) {
            // $table maps char→entity; we want entity→char
            $entities[$entity] = $char;
        }

        // Supplement with the extended HTML5 entity list using html_entity_decode.
        // We probe a curated list of named entities that appear in real-world text.
        $namedEntities = [
            '&hellip;'  => '…',  '&mdash;'   => '—',  '&ndash;'  => '–',
            '&lsquo;'   => "\u{2018}", '&rsquo;' => "\u{2019}",
            '&ldquo;'   => "\u{201C}", '&rdquo;' => "\u{201D}",
            '&laquo;'   => '«',  '&raquo;'   => '»',
            '&bull;'    => '•',  '&middot;'  => '·',
            '&trade;'   => '™',  '&copy;'    => '©',
            '&reg;'     => '®',  '&euro;'    => '€',
            '&pound;'   => '£',  '&yen;'     => '¥',
            '&cent;'    => '¢',  '&sect;'    => '§',
            '&para;'    => '¶',  '&dagger;'  => '†',
            '&Dagger;'  => '‡',  '&permil;'  => '‰',
            '&lsaquo;'  => '‹',  '&rsaquo;'  => '›',
            '&sbquo;'   => '‚',  '&bdquo;'   => '„',
            '&fnof;'    => 'ƒ',  '&circ;'    => 'ˆ',
            '&tilde;'   => '˜',  '&OElig;'   => 'Œ',
            '&oelig;'   => 'œ',  '&Scaron;'  => 'Š',
            '&scaron;'  => 'š',  '&Yuml;'    => 'Ÿ',
            '&macr;'    => '¯',  '&checkmark;' => '✓',
            '&Jscr;'    => "\u{1D4A5}", '&HilbertSpace;' => "\u{210B}",
            // Greek
            '&Alpha;'   => 'Α',  '&Beta;'    => 'Β',  '&Gamma;'  => 'Γ',
            '&Delta;'   => 'Δ',  '&Epsilon;' => 'Ε',  '&Zeta;'   => 'Ζ',
            '&Eta;'     => 'Η',  '&Theta;'   => 'Θ',  '&Iota;'   => 'Ι',
            '&Kappa;'   => 'Κ',  '&Lambda;'  => 'Λ',  '&Mu;'     => 'Μ',
            '&Nu;'      => 'Ν',  '&Xi;'      => 'Ξ',  '&Omicron;'=> 'Ο',
            '&Pi;'      => 'Π',  '&Rho;'     => 'Ρ',  '&Sigma;'  => 'Σ',
            '&Tau;'     => 'Τ',  '&Upsilon;' => 'Υ',  '&Phi;'    => 'Φ',
            '&Chi;'     => 'Χ',  '&Psi;'     => 'Ψ',  '&Omega;'  => 'Ω',
            '&alpha;'   => 'α',  '&beta;'    => 'β',  '&gamma;'  => 'γ',
            '&delta;'   => 'δ',  '&epsilon;' => 'ε',  '&zeta;'   => 'ζ',
            '&eta;'     => 'η',  '&theta;'   => 'θ',  '&iota;'   => 'ι',
            '&kappa;'   => 'κ',  '&lambda;'  => 'λ',  '&mu;'     => 'μ',
            '&nu;'      => 'ν',  '&xi;'      => 'ξ',  '&omicron;'=> 'ο',
            '&pi;'      => 'π',  '&rho;'     => 'ρ',  '&sigmaf;' => 'ς',
            '&sigma;'   => 'σ',  '&tau;'     => 'τ',  '&upsilon;'=> 'υ',
            '&phi;'     => 'φ',  '&chi;'     => 'χ',  '&psi;'    => 'ψ',
            '&omega;'   => 'ω',
        ];
        foreach ($namedEntities as $entity => $char) {
            $entities[$entity] = $char;
        }

        // Add uppercase variants for latin + common symbol entities (as Python does).
        $uppercaseAble = [];
        foreach ($entities as $entity => $char) {
            // Only consider entities that are all-lowercase after the &
            $name = substr($entity, 1, -1); // strip & and ;
            if ($name === strtolower($name) && strlen($name) >= 2) {
                $upper = '&' . strtoupper($name) . ';';
                // Only add if PHP's html_entity_decode wouldn't already handle it.
                if (!isset($entities[$upper])) {
                    $upperChar = mb_strtoupper($char, 'UTF-8');
                    if ($upperChar !== $char) {
                        $uppercaseAble[$upper] = $upperChar;
                    }
                }
            }
        }
        foreach ($uppercaseAble as $entity => $char) {
            $entities[$entity] = $char;
        }

        return $entities;
    }

    // -------------------------------------------------------------------------
    // UTF-8 detector regex (for decode_inconsistent_utf8)
    // -------------------------------------------------------------------------

    /**
     * Regex that matches sequences of characters that look like UTF-8 mojibake
     * embedded in otherwise-correct text.
     *
     * This is the PHP equivalent of chardata.UTF8_DETECTOR_RE.
     */
    public static function getUtf8DetectorRegex(): string
    {
        // Character classes from UTF8_CLUES in chardata.py, expressed as
        // PCRE Unicode escapes.

        // Letters that decode to 0xC2-0xDF in Latin-1-like encodings (first byte of 2-byte UTF-8)
        $first2 =
            '\x{00C2}\x{00C3}\x{00C5}\x{00CE}\x{00D0}\x{00D9}'   // common Latin subsets
            . '\x{00C0}-\x{00D9}'      // Latin-1 upper accented (C0-D9, the main range)
            . '\x{0102}\x{0100}\x{0106}\x{010C}\x{010E}\x{0110}'  // windows-1250 specific
            . '\x{0118}\x{011A}\x{012A}\x{0141}\x{0143}\x{0147}'
            . '\x{0154}\x{015A}\x{015E}\x{0160}\x{0164}\x{0166}'
            . '\x{016E}\x{0170}\x{0179}\x{017B}\x{017D}'
            // Greek (windows-1253 C2-DF range)
            . '\x{0392}-\x{03A9}\x{03AA}\x{03AB}\x{03AC}-\x{03AF}'
            // Cyrillic (windows-1251 C2-DF range)
            . '\x{0412}-\x{042F}';

        // Letters that decode to 0xE0-0xEF (first byte of 3-byte UTF-8)
        $first3 =
            '\x{00E0}-\x{00EF}'        // Latin-1 lower accented
            . '\x{0103}\x{0101}\x{0107}\x{010D}\x{010F}\x{0111}'
            . '\x{0119}\x{011B}\x{012B}\x{013C}\x{013A}\x{0142}'
            . '\x{0144}\x{0148}\x{0155}\x{015B}\x{015F}\x{0161}'
            . '\x{0165}\x{017A}\x{017C}\x{017E}\x{0219}\x{021B}'
            // Greek small (windows-1253 E0-EF)
            . '\x{03B0}-\x{03BF}'
            // Cyrillic small (windows-1251 E0-EF)
            . '\x{0430}-\x{043F}';

        // Letters that decode to 0xF0 or 0xF3 (first byte of 4-byte UTF-8)
        $first4 =
            '\x{00F0}\x{00F3}'         // Latin-1 ð ó
            . '\x{0111}\x{011F}\x{015B}'  // d-stroke, g-breve, s-caron
            . '\x{03C0}\x{03C3}'       // Greek pi, sigma
            . '\x{0440}\x{0443}';      // Cyrillic er, u

        // Continuation byte stand-ins (0x80-0xBF in Latin-1-like encodings + space)
        $cont =
            '\x{0080}-\x{00BF}'        // direct C1 / Latin-1 upper range
            . '\x{0020}'               // space standing in for 0xA0
            // A selection of characters from various windows encodings
            // that appear in the 0x80-0xBF byte positions
            . '\x{0104}\x{00C6}\x{0139}\x{0141}\x{00D8}\x{0342}\x{0343}'
            . '\x{0145}\x{013B}\x{015A}\x{0160}\x{015C}\x{0166}\x{0178}'
            . '\x{017B}\x{017D}\x{0179}\x{0152}\x{0105}\x{00E6}'
            . '\x{0192}\x{013A}\x{0142}\x{00F8}\x{0146}\x{013C}'
            . '\x{015B}\x{0161}\x{015D}\x{0167}\x{017A}\x{017C}\x{017E}'
            . '\x{0153}\x{02C6}\x{02C7}\x{02D8}\x{02DB}\x{02DC}\x{02DD}'
            . '\x{0384}\x{0385}\x{0386}\x{0388}\x{0389}\x{038A}\x{038C}'
            . '\x{038E}\x{038F}\x{0400}\x{0402}-\x{040F}\x{0450}\x{0452}-\x{045F}'
            . '\x{0490}\x{0491}'
            . '\x{2013}\x{2014}\x{2015}\x{2018}\x{2019}\x{201A}'
            . '\x{201C}\x{201D}\x{201E}\x{2020}\x{2021}\x{2022}'
            . '\x{2026}\x{2030}\x{2039}\x{203A}\x{20AC}\x{2116}\x{2122}';

        // The lookbehind uses a stricter continuation set (no spaces/dashes/quotes).
        $contStrict =
            '\x{0080}-\x{00BF}'
            . '\x{0104}\x{00C6}\x{0139}\x{0141}\x{00D8}\x{0145}\x{013B}'
            . '\x{015A}\x{0160}\x{015C}\x{0166}\x{0178}\x{017B}\x{017D}'
            . '\x{0179}\x{0152}\x{0105}\x{00E6}\x{0192}\x{013A}\x{0142}'
            . '\x{00F8}\x{0146}\x{013C}\x{015B}\x{0161}\x{015D}\x{0167}'
            . '\x{017A}\x{017C}\x{017E}\x{0153}\x{02C6}\x{02C7}\x{02D8}'
            . '\x{02DB}\x{02DC}\x{02DD}\x{0384}\x{0385}\x{0386}\x{0388}'
            . '\x{0389}\x{038A}\x{038C}\x{038E}\x{038F}'
            . '\x{0400}\x{0402}-\x{040F}\x{0450}\x{0452}-\x{045F}'
            . '\x{0490}\x{0491}'
            . '\x{2020}\x{2021}\x{2030}\x{2039}\x{203A}\x{20AC}\x{2116}\x{2122}';

        return
            '/(?<![' . $contStrict . '])'
            . '(?:'
            .   '[' . $first2 . '][' . $cont . ']'
            .   '|[' . $first3 . '][' . $cont . ']{2}'
            .   '|[' . $first4 . '][' . $cont . ']{3}'
            . ')+/u';
    }

    // -------------------------------------------------------------------------
    // Width map (fullwidth/halfwidth → standard)
    // -------------------------------------------------------------------------

    /** @var array<int,string>|null */
    private static ?array $widthMap = null;

    /**
     * Returns a map of [codepoint => replacement_string] for fullwidth/halfwidth
     * characters. Also includes U+3000 (ideographic space) → U+0020.
     *
     * @return array<int,string>
     */
    public static function getWidthMap(): array
    {
        if (self::$widthMap !== null) {
            return self::$widthMap;
        }

        $map = [0x3000 => ' '];

        // Fullwidth ASCII variants: U+FF01-U+FF5E → ASCII U+0021-U+007E
        for ($i = 0xFF01; $i <= 0xFF5E; $i++) {
            $map[$i] = mb_chr($i - 0xFF01 + 0x21, 'UTF-8');
        }

        // Halfwidth Katakana: U+FF65-U+FF9F → fullwidth Katakana equivalents.
        // We use PHP's Normalizer (NFKC) to compute these.
        for ($i = 0xFF65; $i <= 0xFF9F; $i++) {
            $char = mb_chr($i, 'UTF-8');
            $norm = \Normalizer::normalize($char, \Normalizer::FORM_KC);
            if ($norm !== false && $norm !== $char) {
                $map[$i] = $norm;
            }
        }

        // Halfwidth Hangul filler etc.
        for ($i = 0xFFA0; $i <= 0xFFEF; $i++) {
            $char = mb_chr($i, 'UTF-8');
            $norm = \Normalizer::normalize($char, \Normalizer::FORM_KC);
            if ($norm !== false && $norm !== $char) {
                $map[$i] = $norm;
            }
        }

        self::$widthMap = $map;
        return $map;
    }
}
