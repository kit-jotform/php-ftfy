<?php

declare(strict_types=1);

namespace Ftfy;

use Ftfy\Codecs\SloppyCodecs;

/** Individual text-fixing functions. Port of ftfy/fixes.py. */
final class Fixes
{
    /** Decode HTML entities (&amp;, &#x41;, &hellip;, etc.) to their Unicode equivalents. */
    public static function unescapeHtml(string $text): string
    {
        $entities = CharData::getHtmlEntities();

        return preg_replace_callback(
            CharData::HTML_ENTITY_RE,
            static function (array $m) use ($entities): string {
                $entity = $m[0];

                if (isset($entities[$entity])) {
                    return $entities[$entity];
                }

                if (str_starts_with($entity, '&#')) {
                    $decoded = html_entity_decode($entity, ENT_HTML5 | ENT_QUOTES, 'UTF-8');
                    if (!str_contains($decoded, ';')) {
                        return $decoded;
                    }
                }

                return $entity;
            },
            $text
        ) ?? $text;
    }

    /** Strip ANSI/VT100 terminal escape sequences (e.g. colour codes). */
    public static function removeTerminalEscapes(string $text): string
    {
        return preg_replace('/\033\[((?:\d|;)*)([a-zA-Z])/', '', $text) ?? $text;
    }

    /** Replace typographic (curly) quotes with straight ASCII equivalents. */
    public static function uncurlQuotes(string $text): string
    {
        $text = preg_replace(CharData::SINGLE_QUOTE_RE, "'", $text) ?? $text;
        $text = preg_replace(CharData::DOUBLE_QUOTE_RE, '"', $text) ?? $text;
        return $text;
    }

    /** Expand Latin ligatures (ff, fi, fl …) and compatibility digraphs to their component letters. */
    public static function fixLatinLigatures(string $text): string
    {
        $ligatures = CharData::LIGATURES;
        return preg_replace_callback(
            '/[\x{FB00}-\x{FB06}\x{0132}\x{0133}\x{0149}\x{01C4}-\x{01CC}\x{01F1}-\x{01F3}]/u',
            static function (array $m) use ($ligatures): string {
                $cp = mb_ord($m[0], 'UTF-8');
                return $ligatures[$cp] ?? $m[0];
            },
            $text
        ) ?? $text;
    }

    /** Normalize fullwidth/halfwidth Unicode variants (U+FF01–U+FFEF) and ideographic space (U+3000) to their standard-width equivalents. */
    public static function fixCharacterWidth(string $text): string
    {
        $widthMap = CharData::getWidthMap();
        return preg_replace_callback(
            '/[\x{3000}\x{FF01}-\x{FFEF}]/u',
            static function (array $m) use ($widthMap): string {
                $cp = mb_ord($m[0], 'UTF-8');
                return $widthMap[$cp] ?? $m[0];
            },
            $text
        ) ?? $text;
    }

    /** Normalize all line-break variants (CRLF, CR, U+2028, U+2029, U+0085) to LF. */
    public static function fixLineBreaks(string $text): string
    {
        // Normalize CRLF first so \r\n doesn't leave a stray \r.
        $text = str_replace("\r\n", "\n", $text);
        return str_replace(
            ["\r", "\u{2028}", "\u{2029}", "\u{0085}"],
            "\n",
            $text
        );
    }

    /** Decode CESU-8 surrogate pairs to proper UTF-8 and replace isolated surrogates with U+FFFD. */
    public static function fixSurrogates(string $text): string
    {
        // PCRE /u rejects surrogates, so decode CESU-8 at the byte level.
        // ED [A0-AF] xx = high surrogate, ED [B0-BF] xx = low surrogate.
        if (!str_contains($text, "\xED")) {
            return $text;
        }

        $bytes = $text;
        $chunks = [];
        $len = strlen($bytes);
        $i = 0;
        $copyFrom = 0;
        while ($i < $len) {
            if (ord($bytes[$i]) !== 0xED) {
                $i++;
                continue;
            }
            if (
                $i + 5 < $len
                && (ord($bytes[$i + 1]) & 0xF0) === 0xA0
                && (ord($bytes[$i + 2]) & 0xC0) === 0x80
                && ord($bytes[$i + 3]) === 0xED
                && (ord($bytes[$i + 4]) & 0xF0) === 0xB0
                && (ord($bytes[$i + 5]) & 0xC0) === 0x80
            ) {
                if ($i > $copyFrom) {
                    $chunks[] = substr($bytes, $copyFrom, $i - $copyFrom);
                }
                $high = 0xD000 | ((ord($bytes[$i + 1]) & 0x3F) << 6) | (ord($bytes[$i + 2]) & 0x3F);
                $low  = 0xD000 | ((ord($bytes[$i + 4]) & 0x3F) << 6) | (ord($bytes[$i + 5]) & 0x3F);
                $cp = 0x10000 + (($high - 0xD800) << 10) + ($low - 0xDC00);
                $chunks[] = mb_chr($cp, 'UTF-8');
                $i += 6;
                $copyFrom = $i;
                continue;
            }
            // Isolated surrogate (high or low) → replacement character
            if (
                $i + 2 < $len
                && (ord($bytes[$i + 1]) & 0xE0) === 0xA0
                && (ord($bytes[$i + 2]) & 0xC0) === 0x80
            ) {
                if ($i > $copyFrom) {
                    $chunks[] = substr($bytes, $copyFrom, $i - $copyFrom);
                }
                $chunks[] = "\u{FFFD}";
                $i += 3;
                $copyFrom = $i;
                continue;
            }
            if (
                $i + 2 < $len
                && (ord($bytes[$i + 1]) & 0xE0) === 0xB0
                && (ord($bytes[$i + 2]) & 0xC0) === 0x80
            ) {
                if ($i > $copyFrom) {
                    $chunks[] = substr($bytes, $copyFrom, $i - $copyFrom);
                }
                $chunks[] = "\u{FFFD}";
                $i += 3;
                $copyFrom = $i;
                continue;
            }
            $i++;
        }
        if ($copyFrom < $len) {
            $chunks[] = substr($bytes, $copyFrom);
        }

        return implode('', $chunks);
    }

    /** Remove C0/C1 control characters and invisible format characters from text. */
    public static function removeControlChars(string $text): string
    {
        $codepoints = CharData::CONTROL_CHAR_CODEPOINTS;

        static $pattern = null;
        if ($pattern === null) {
            $parts = [];
            foreach ($codepoints as $cp) {
                $parts[] = sprintf('\x{%04X}', $cp);
            }
            $pattern = '/[' . implode('', $parts) . ']/u';
        }

        return preg_replace($pattern, '', $text) ?? $text;
    }

    /**
     * In a raw byte string, restore 0x20 (space) to 0xA0 (no-break space)
     * where it completes a valid UTF-8 sequence. Mirrors fixes.restore_byte_a0.
     */
    public static function restoreByteA0(string $bytes): string
    {
        // "à word" exception: C3 followed by a space before a non-contraction word.
        $bytes = preg_replace_callback(
            '/\xC3 (?! |quele|quela|quilo|s )/',
            static fn() => "\xC3\xA0 ",
            $bytes
        ) ?? $bytes;

        $bytes = preg_replace_callback(
            CharData::ALTERED_UTF8_RE,
            static function (array $m): string {
                return str_replace("\x20", "\xA0", $m[0]);
            },
            $bytes
        ) ?? $bytes;

        return $bytes;
    }

    /**
     * Replace incomplete UTF-8 sequences — where continuation bytes were substituted
     * with 0x1A or '?' by a lossy codec — with U+FFFD. Operates on raw byte strings.
     */
    public static function replaceLossySequences(string $bytes): string
    {
        return preg_replace(CharData::LOSSY_UTF8_RE, "\xEF\xBF\xBD", $bytes) ?? $bytes;
    }

    /** Recursively re-decode mojibake sequences embedded within otherwise-correct UTF-8. */
    public static function decodeInconsistentUtf8(string $text): string
    {
        $detectorRegex = CharData::getUtf8DetectorRegex();
        $textLen = strlen($text);
        return preg_replace_callback(
            $detectorRegex,
            static function (array $m) use ($textLen): string {
                $substr = $m[0];
                // Guard against infinite recursion: only recurse when the match
                // is strictly shorter than the full input.
                if (strlen($substr) < $textLen && Badness::isBad($substr)) {
                    return Ftfy::fixEncoding($substr);
                }
                return $substr;
            },
            $text
        ) ?? $text;
    }

    /** @var array<string,string>|null */
    private static ?array $c1Map = null;

    /** Reinterpret C1 control characters (U+0080–U+009F) as Windows-1252 printable characters. */
    public static function fixC1Controls(string $text): string
    {
        if (self::$c1Map === null) {
            self::$c1Map = [];
            for ($byte = 0x80; $byte <= 0x9F; $byte++) {
                $utf8Char = mb_chr($byte, 'UTF-8');
                self::$c1Map[$utf8Char] = SloppyCodecs::decode(chr($byte), 'windows-1252');
            }
        }

        return strtr($text, self::$c1Map);
    }

    /** Strip leading byte-order marks (U+FEFF). */
    public static function removeBom(string $text): string
    {
        return ltrim($text, "\u{FEFF}");
    }
}
