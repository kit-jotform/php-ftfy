<?php

declare(strict_types=1);

namespace Ftfy\Codecs;

/**
 * Decoder for "utf-8-variants" (CESU-8 / Java modified UTF-8).
 *
 * Handles:
 * - Standard UTF-8
 * - CESU-8 surrogate pairs: ED A0 xx ED B0 xx  →  single codepoint > U+FFFF
 * - Java null encoding: C0 80  →  U+0000
 * - Isolated surrogates (passed through as-is via mb_chr)
 */
final class Utf8Variants
{
    /**
     * Decode a byte string that may contain CESU-8 / Java-modified UTF-8.
     */
    public static function decode(string $bytes): string
    {
        $out = '';
        $len = strlen($bytes);
        $i = 0;

        while ($i < $len) {
            $b = ord($bytes[$i]);

            // Java null: C0 80  →  U+0000
            if ($b === 0xC0 && $i + 1 < $len && ord($bytes[$i + 1]) === 0x80) {
                $out .= "\u{0000}";
                $i += 2;
                continue;
            }

            // CESU-8 surrogate pair: ED [A0-AF] xx ED [B0-BF] xx
            if (
                $b === 0xED
                && $i + 5 < $len
                && (ord($bytes[$i + 1]) & 0xF0) === 0xA0  // high surrogate lead
                && (ord($bytes[$i + 2]) & 0xC0) === 0x80
                && ord($bytes[$i + 3]) === 0xED
                && (ord($bytes[$i + 4]) & 0xF0) === 0xB0  // low surrogate lead
                && (ord($bytes[$i + 5]) & 0xC0) === 0x80
            ) {
                $high = (($b & 0x0F) << 12) | ((ord($bytes[$i + 1]) & 0x3F) << 6) | (ord($bytes[$i + 2]) & 0x3F);
                $low  = ((ord($bytes[$i + 3]) & 0x0F) << 12) | ((ord($bytes[$i + 4]) & 0x3F) << 6) | (ord($bytes[$i + 5]) & 0x3F);
                // $high is in 0xD800-0xDBFF, $low is in 0xDC00-0xDFFF
                $codepoint = 0x10000 + (($high - 0xD800) << 10) + ($low - 0xDC00);
                $out .= mb_chr($codepoint, 'UTF-8');
                $i += 6;
                continue;
            }

            // Standard UTF-8: determine sequence length from lead byte.
            if ($b < 0x80) {
                $seqLen = 1;
            } elseif (($b & 0xE0) === 0xC0) {
                $seqLen = 2;
            } elseif (($b & 0xF0) === 0xE0) {
                $seqLen = 3;
            } elseif (($b & 0xF8) === 0xF0) {
                $seqLen = 4;
            } else {
                // Continuation byte or invalid — output replacement char and advance.
                $out .= "\u{FFFD}";
                $i++;
                continue;
            }

            if ($i + $seqLen > $len) {
                // Truncated sequence at end of input.
                $out .= "\u{FFFD}";
                $i = $len;
                continue;
            }

            $seq = substr($bytes, $i, $seqLen);
            $decoded = @mb_convert_encoding($seq, 'UTF-8', 'UTF-8');
            if ($decoded === false || $decoded === '') {
                $out .= "\u{FFFD}";
            } else {
                $out .= $decoded;
            }
            $i += $seqLen;
        }

        return $out;
    }
}
