<?php

declare(strict_types=1);

namespace Ftfy;

use Ftfy\Codecs\SloppyCodecs;

/**
 * Individual text-fixing functions. Port of ftfy/fixes.py.
 *
 * All functions accept and return UTF-8 strings, except where raw bytes
 * are explicitly noted.
 */
final class Fixes
{
    // -------------------------------------------------------------------------
    // HTML unescaping
    // -------------------------------------------------------------------------

    public static function unescapeHtml(string $text): string
    {
        $entities = CharData::getHtmlEntities();

        return preg_replace_callback(
            CharData::HTML_ENTITY_RE,
            static function (array $m) use ($entities): string {
                $entity = $m[0];

                // Named entity lookup (includes uppercase variants)
                if (isset($entities[$entity])) {
                    return $entities[$entity];
                }

                // Numeric entities: &#NNN; or &#xHHH;
                if (str_starts_with($entity, '&#')) {
                    $decoded = html_entity_decode($entity, ENT_HTML5 | ENT_QUOTES, 'UTF-8');
                    // If the semicolon was consumed (i.e. entity was fully decoded)
                    if (!str_contains($decoded, ';')) {
                        return $decoded;
                    }
                }

                return $entity;
            },
            $text
        ) ?? $text;
    }

    // -------------------------------------------------------------------------
    // Terminal escapes
    // -------------------------------------------------------------------------

    public static function removeTerminalEscapes(string $text): string
    {
        return preg_replace('/\033\[((?:\d|;)*)([a-zA-Z])/', '', $text) ?? $text;
    }

    // -------------------------------------------------------------------------
    // Quote normalisation
    // -------------------------------------------------------------------------

    public static function uncurlQuotes(string $text): string
    {
        $text = preg_replace(CharData::SINGLE_QUOTE_RE, "'", $text) ?? $text;
        $text = preg_replace(CharData::DOUBLE_QUOTE_RE, '"', $text) ?? $text;
        return $text;
    }

    // -------------------------------------------------------------------------
    // Latin ligatures
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Character width
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Line breaks
    // -------------------------------------------------------------------------

    public static function fixLineBreaks(string $text): string
    {
        // Order matters: CRLF before CR
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        $text = str_replace("\u{2028}", "\n", $text); // LINE SEPARATOR
        $text = str_replace("\u{2029}", "\n", $text); // PARAGRAPH SEPARATOR
        $text = str_replace("\u{0085}", "\n", $text); // NEXT LINE
        return $text;
    }

    // -------------------------------------------------------------------------
    // Surrogate pairs
    // -------------------------------------------------------------------------

    public static function fixSurrogates(string $text): string
    {
        // Scan for surrogate sequences encoded as CESU-8 bytes.
        // PCRE /u mode rejects surrogates, so we work at the byte level.
        // ED [A0-AF] xx  = high surrogate
        // ED [B0-BF] xx  = low surrogate
        if (!str_contains($text, "\xED")) {
            return $text;
        }

        // Convert via CESU-8 decoder on the raw bytes.
        // In PHP, a string that contains surrogates as UTF-8 bytes (CESU-8) will
        // have the bytes \xED\xA0...\xED\xB0... We detect and convert these.
        $bytes = $text; // already raw bytes in PHP
        $out = '';
        $len = strlen($bytes);
        $i = 0;
        while ($i < $len) {
            if (
                ord($bytes[$i]) === 0xED
                && $i + 5 < $len
                && (ord($bytes[$i + 1]) & 0xF0) === 0xA0
                && (ord($bytes[$i + 2]) & 0xC0) === 0x80
                && ord($bytes[$i + 3]) === 0xED
                && (ord($bytes[$i + 4]) & 0xF0) === 0xB0
                && (ord($bytes[$i + 5]) & 0xC0) === 0x80
            ) {
                $high = 0xD000 | ((ord($bytes[$i + 1]) & 0x3F) << 6) | (ord($bytes[$i + 2]) & 0x3F);
                $low  = 0xD000 | ((ord($bytes[$i + 4]) & 0x3F) << 6) | (ord($bytes[$i + 5]) & 0x3F);
                // $high ∈ D800-DBFF, $low ∈ DC00-DFFF
                $cp = 0x10000 + (($high - 0xD800) << 10) + ($low - 0xDC00);
                $out .= mb_chr($cp, 'UTF-8');
                $i += 6;
                continue;
            }
            // Isolated high surrogate: ED [A0-AF] xx
            if (
                ord($bytes[$i]) === 0xED
                && $i + 2 < $len
                && (ord($bytes[$i + 1]) & 0xE0) === 0xA0
                && (ord($bytes[$i + 2]) & 0xC0) === 0x80
            ) {
                $out .= "\u{FFFD}";
                $i += 3;
                continue;
            }
            // Isolated low surrogate: ED [B0-BF] xx
            if (
                ord($bytes[$i]) === 0xED
                && $i + 2 < $len
                && (ord($bytes[$i + 1]) & 0xE0) === 0xB0
                && (ord($bytes[$i + 2]) & 0xC0) === 0x80
            ) {
                $out .= "\u{FFFD}";
                $i += 3;
                continue;
            }

            $out .= $bytes[$i];
            $i++;
        }

        return $out;
    }

    // -------------------------------------------------------------------------
    // Control character removal
    // -------------------------------------------------------------------------

    public static function removeControlChars(string $text): string
    {
        $codepoints = CharData::CONTROL_CHAR_CODEPOINTS;

        // Build a regex from the codepoint list (cached).
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

    // -------------------------------------------------------------------------
    // Restore byte 0xA0 (operates on raw bytes)
    // -------------------------------------------------------------------------

    /**
     * In a bytes string, restore 0x20 (space) to 0xA0 (no-break space) where
     * it would complete a valid UTF-8 sequence. Mirrors fixes.restore_byte_a0.
     *
     * @param string $bytes Raw binary string
     * @return string Raw binary string
     */
    public static function restoreByteA0(string $bytes): string
    {
        // Handle the "à word" exception: C3 followed by single space before
        // a non-contraction word.
        $bytes = preg_replace_callback(
            '/\xC3 (?! |quele|quela|quilo|s )/',
            static fn() => "\xC3\xA0 ",
            $bytes
        ) ?? $bytes;

        // Replace space with 0xA0 inside ALTERED_UTF8_RE matches.
        $bytes = preg_replace_callback(
            CharData::ALTERED_UTF8_RE,
            static function (array $m): string {
                return str_replace("\x20", "\xA0", $m[0]);
            },
            $bytes
        ) ?? $bytes;

        return $bytes;
    }

    // -------------------------------------------------------------------------
    // Replace lossy sequences (operates on raw bytes)
    // -------------------------------------------------------------------------

    /**
     * Replace LOSSY_UTF8_RE matches with the UTF-8 encoding of U+FFFD.
     *
     * @param string $bytes Raw binary string
     * @return string Raw binary string
     */
    public static function replaceLossySequences(string $bytes): string
    {
        $replacement = "\xEF\xBF\xBD"; // UTF-8 for U+FFFD
        return preg_replace(CharData::LOSSY_UTF8_RE, $replacement, $bytes) ?? $bytes;
    }

    // -------------------------------------------------------------------------
    // Decode inconsistent UTF-8
    // -------------------------------------------------------------------------

    public static function decodeInconsistentUtf8(string $text): string
    {
        $detectorRegex = CharData::getUtf8DetectorRegex();
        return preg_replace_callback(
            $detectorRegex,
            static function (array $m) use ($detectorRegex): string {
                $substr = $m[0];
                if (strlen($substr) < strlen($detectorRegex) && Badness::isBad($substr)) {
                    // Recursion guard: only fix if shorter than the full text
                    return Ftfy::fixEncoding($substr);
                }
                return $substr;
            },
            $text
        ) ?? $text;
    }

    // -------------------------------------------------------------------------
    // Fix C1 controls
    // -------------------------------------------------------------------------

    public static function fixC1Controls(string $text): string
    {
        // Replace C1 control characters (U+0080–U+009F) with their Windows-1252
        // equivalents (same as what HTML5 browsers do).
        return preg_replace_callback(
            CharData::C1_CONTROL_RE,
            static function (array $m): string {
                // Encode the character as Latin-1 byte, decode as sloppy-windows-1252
                $byte = mb_ord($m[0], 'UTF-8');
                if ($byte >= 0x80 && $byte <= 0x9F) {
                    return SloppyCodecs::decode(chr($byte), 'windows-1252');
                }
                return $m[0];
            },
            $text
        ) ?? $text;
    }

    // -------------------------------------------------------------------------
    // BOM removal
    // -------------------------------------------------------------------------

    public static function removeBom(string $text): string
    {
        return ltrim($text, "\u{FEFF}");
    }
}
