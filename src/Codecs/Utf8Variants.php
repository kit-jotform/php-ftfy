<?php

declare(strict_types=1);

namespace Ftfy\Codecs;

/**
 * Decoder for "utf-8-variants" (CESU-8 / Java modified UTF-8).
 *
 * Handles:
 * - CESU-8 surrogate pairs: ED A0 xx ED B0 xx  →  single codepoint > U+FFFF
 * - Java null encoding: C0 80  →  U+0000
 * - Isolated surrogates → U+FFFD
 */
final class Utf8Variants
{
    public static function decode(string $bytes): string
    {
        $chunks = [];
        $len = strlen($bytes);
        $i = 0;
        $copyFrom = 0;

        while ($i < $len) {
            $b = ord($bytes[$i]);

            // Java null: C0 80 → U+0000
            if ($b === 0xC0 && $i + 1 < $len && ord($bytes[$i + 1]) === 0x80) {
                if ($i > $copyFrom) {
                    $chunks[] = substr($bytes, $copyFrom, $i - $copyFrom);
                }
                $chunks[] = "\u{0000}";
                $i += 2;
                $copyFrom = $i;
                continue;
            }

            // CESU-8 surrogate pair: ED [A0-AF] xx ED [B0-BF] xx
            if (
                $b === 0xED
                && $i + 5 < $len
                && (ord($bytes[$i + 1]) & 0xF0) === 0xA0
                && (ord($bytes[$i + 2]) & 0xC0) === 0x80
                && ord($bytes[$i + 3]) === 0xED
                && (ord($bytes[$i + 4]) & 0xF0) === 0xB0
                && (ord($bytes[$i + 5]) & 0xC0) === 0x80
            ) {
                if ($i > $copyFrom) {
                    $chunks[] = substr($bytes, $copyFrom, $i - $copyFrom);
                }
                $high = (($b & 0x0F) << 12) | ((ord($bytes[$i + 1]) & 0x3F) << 6) | (ord($bytes[$i + 2]) & 0x3F);
                // phpcs:ignore Generic.Files.LineLength
                $low  = ((ord($bytes[$i + 3]) & 0x0F) << 12) | ((ord($bytes[$i + 4]) & 0x3F) << 6) | (ord($bytes[$i + 5]) & 0x3F);
                $codepoint = 0x10000 + (($high - 0xD800) << 10) + ($low - 0xDC00);
                $chunks[] = mb_chr($codepoint, 'UTF-8');
                $i += 6;
                $copyFrom = $i;
                continue;
            }

            if ($b < 0x80) {
                $i++;
                continue;
            } elseif (($b & 0xE0) === 0xC0) {
                $seqLen = 2;
            } elseif (($b & 0xF0) === 0xE0) {
                $seqLen = 3;
            } elseif (($b & 0xF8) === 0xF0) {
                $seqLen = 4;
            } else {
                // Continuation byte or invalid lead byte.
                if ($i > $copyFrom) {
                    $chunks[] = substr($bytes, $copyFrom, $i - $copyFrom);
                }
                $chunks[] = "\u{FFFD}";
                $i++;
                $copyFrom = $i;
                continue;
            }

            if ($i + $seqLen > $len) {
                // Truncated sequence at end of input.
                if ($i > $copyFrom) {
                    $chunks[] = substr($bytes, $copyFrom, $i - $copyFrom);
                }
                $chunks[] = "\u{FFFD}";
                $i = $len;
                $copyFrom = $i;
                continue;
            }

            $i += $seqLen;
        }

        if ($copyFrom === 0) {
            return $bytes;
        }

        if ($copyFrom < $len) {
            $chunks[] = substr($bytes, $copyFrom);
        }

        return implode('', $chunks);
    }
}
