<?php

declare(strict_types=1);

namespace Ftfy;

use Ftfy\Codecs\SloppyCodecs;
use Ftfy\Codecs\Utf8Variants;

/**
 * Main entry point for ftfy. Port of ftfy/__init__.py.
 *
 * Primary public API:
 *   Ftfy::fixText(string $text, ?TextFixerConfig $config): string
 *   Ftfy::fixEncoding(string $text, ?TextFixerConfig $config): string
 *   Ftfy::fixAndExplain(string $text, ?TextFixerConfig $config): array{string, ?array}
 */
final class Ftfy
{
    public const VERSION = '6.3.1-php';

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Fix Unicode problems in text, returning the corrected string.
     *
     * Text is processed in line-length segments (split on "\n") to bound
     * worst-case runtime, exactly as the Python version does.
     */
    public static function fixText(string $text, ?TextFixerConfig $config = null): string
    {
        $config ??= new TextFixerConfig(explain: false);
        $config = $config->with(explain: false);

        $out = [];
        $pos = 0;
        $len = mb_strlen($text, 'UTF-8');

        while ($pos < $len) {
            $nlPos = mb_strpos($text, "\n", $pos, 'UTF-8');
            $textbreak = ($nlPos === false) ? $len : $nlPos + 1;

            if (($textbreak - $pos) > $config->maxDecodeLength) {
                $textbreak = $pos + $config->maxDecodeLength;
            }

            $segment = mb_substr($text, $pos, $textbreak - $pos, 'UTF-8');

            if ($config->unescapeHtml === 'auto' && str_contains($segment, '<')) {
                $config = $config->with(unescapeHtml: false);
            }

            [$fixed] = self::fixAndExplain($segment, $config);
            $out[] = $fixed;
            $pos = $textbreak;
        }

        return implode('', $out);
    }

    /**
     * Fix text as a single segment, returning [fixedText, explanation].
     *
     * $explanation is null when config->explain is false.
     *
     * @return array{string, list<array{string,string}>|null}
     */
    public static function fixAndExplain(string $text, ?TextFixerConfig $config = null): array
    {
        $config ??= new TextFixerConfig();

        if ($config->unescapeHtml === 'auto' && str_contains($text, '<')) {
            $config = $config->with(unescapeHtml: false);
        }

        $steps = $config->explain ? [] : null;

        $normConst = null;
        if ($config->normalization !== null) {
            $normConst = match ($config->normalization) {
                'NFC'  => \Normalizer::FORM_C,
                'NFD'  => \Normalizer::FORM_D,
                'NFKC' => \Normalizer::FORM_KC,
                'NFKD' => \Normalizer::FORM_KD,
                default => \Normalizer::FORM_C,
            };
        }

        while (true) {
            $origText = $text;

            // 1. HTML entities
            $text = self::tryFix('unescapeHtml', $text, $config, $steps);

            // 2. Encoding fix (mojibake)
            if ($config->fixEncoding) {
                if ($steps === null) {
                    $text = self::fixEncoding($text, $config);
                } else {
                    [$text, $encSteps] = self::fixEncodingAndExplain($text, $config);
                    if ($encSteps !== null) {
                        foreach ($encSteps as $step) {
                            $steps[] = $step;
                        }
                    }
                }
            }

            // 3. Character-level fixers
            foreach (
                [
                    'fixC1Controls',
                    'fixLatinLigatures',
                    'fixCharacterWidth',
                    'uncurlQuotes',
                    'fixLineBreaks',
                    'fixSurrogates',
                    'removeTerminalEscapes',
                    'removeControlChars',
                ] as $fixer
            ) {
                $text = self::tryFix($fixer, $text, $config, $steps);
            }

            // 4. Unicode normalisation
            if ($normConst !== null) {
                $normalised = \Normalizer::normalize($text, $normConst);
                if ($normalised !== false && $normalised !== $text) {
                    if ($steps !== null) {
                        $steps[] = ['normalize', $config->normalization];
                    }
                    $text = $normalised;
                }
            }

            if ($text === $origText) {
                return [$text, $steps];
            }
        }
    }

    /**
     * Apply only the encoding-fixing steps (mojibake detection/repair).
     *
     * @return array{string, list<array{string,string}>|null}
     */
    public static function fixEncodingAndExplain(string $text, ?TextFixerConfig $config = null): array
    {
        $config ??= new TextFixerConfig();

        if (!$config->fixEncoding) {
            return [$text, []];
        }

        $planSoFar = [];
        while (true) {
            $prev = $text;
            [$text, $plan] = self::fixEncodingOneStep($text, $config);
            if ($plan !== null) {
                foreach ($plan as $step) {
                    $planSoFar[] = $step;
                }
            }
            if ($text === $prev) {
                return [$text, $planSoFar];
            }
        }
    }

    /**
     * Fix encoding only, discarding the explanation.
     */
    public static function fixEncoding(string $text, ?TextFixerConfig $config = null): string
    {
        $config ??= new TextFixerConfig(explain: false);
        [$fixed] = self::fixEncodingAndExplain($text, $config);
        return $fixed;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Apply a named fixer if enabled in config.
     *
     * @param list<array{string,string}>|null $steps
     */
    private static function tryFix(
        string $fixerName,
        string $text,
        TextFixerConfig $config,
        ?array &$steps
    ): string {
        // Map fixer names to config property names and Fixes methods.
        static $configMap = [
            'unescapeHtml'          => 'unescapeHtml',
            'removeTerminalEscapes' => 'removeTerminalEscapes',
            'fixC1Controls'         => 'fixC1Controls',
            'fixLatinLigatures'     => 'fixLatinLigatures',
            'fixCharacterWidth'     => 'fixCharacterWidth',
            'uncurlQuotes'          => 'uncurlQuotes',
            'fixLineBreaks'         => 'fixLineBreaks',
            'fixSurrogates'         => 'fixSurrogates',
            'removeControlChars'    => 'removeControlChars',
        ];

        $configProp = $configMap[$fixerName] ?? $fixerName;
        $enabled = $config->$configProp;

        if (!$enabled) {
            return $text;
        }

        $method = [Fixes::class, $fixerName];
        $fixed = $method($text);

        if ($steps !== null && $fixed !== $text) {
            $steps[] = ['apply', $fixerName];
        }

        return $fixed;
    }

    /**
     * Perform one round of encoding detection and repair.
     *
     * @return array{string, list<array{string,string}>|null}
     */
    private static function fixEncodingOneStep(string $text, TextFixerConfig $config): array
    {
        if ($text === '') {
            return [$text, []];
        }

        // If text is all ASCII, nothing to do.
        if (SloppyCodecs::possibleEncoding($text, 'ascii')) {
            return [$text, []];
        }

        // If text doesn't look like mojibake, nothing to do.
        if (!Badness::isBad($text)) {
            return [$text, []];
        }

        $possible1byteEncodings = [];

        foreach (CharData::CHARMAP_ENCODINGS as $encoding) {
            if (!self::possibleEncodingCheck($text, $encoding)) {
                continue;
            }

            $possible1byteEncodings[] = $encoding;

            // Encode the text back to bytes using this single-byte encoding.
            $encodedBytes = self::encodeToBytes($text, $encoding);
            $encodeStep = ['encode', $encoding];
            $transcodeSteps = [];

            // Try to decode as UTF-8 (or utf-8-variants).
            try {
                $decoding = 'utf-8';

                if (
                    $config->restoreByteA0
                    && $encoding !== 'macroman'
                    && preg_match(CharData::ALTERED_UTF8_RE, $encodedBytes)
                ) {
                    $replaced = Fixes::restoreByteA0($encodedBytes);
                    if ($replaced !== $encodedBytes) {
                        $transcodeSteps[] = ['transcode', 'restoreByteA0'];
                        $encodedBytes = $replaced;
                    }
                }

                if ($config->replaceLossySequences && str_starts_with($encoding, 'sloppy')) {
                    $replaced = Fixes::replaceLossySequences($encodedBytes);
                    if ($replaced !== $encodedBytes) {
                        $transcodeSteps[] = ['transcode', 'replaceLossySequences'];
                        $encodedBytes = $replaced;
                    }
                }

                // Check for bytes that need utf-8-variants decoder.
                if (str_contains($encodedBytes, "\xED") || str_contains($encodedBytes, "\xC0")) {
                    $decoding = 'utf-8-variants';
                }

                $decodeStep = ['decode', $decoding];
                $steps = array_merge([$encodeStep], $transcodeSteps, [$decodeStep]);

                if ($decoding === 'utf-8-variants') {
                    $fixed = Utf8Variants::decode($encodedBytes);
                } else {
                    $fixed = self::strictUtf8Decode($encodedBytes);
                    if ($fixed === null) {
                        continue; // not valid UTF-8
                    }
                }

                return [$fixed, $steps];
            } catch (\ValueError) {
                // Decoding failed; try next encoding.
            }
        }

        // Try decode_inconsistent_utf8
        if ($config->decodeInconsistentUtf8) {
            $detectorRegex = CharData::getUtf8DetectorRegex();
            if (preg_match($detectorRegex, $text)) {
                $fixed = Fixes::decodeInconsistentUtf8($text);
                if ($fixed !== $text) {
                    return [$fixed, [['apply', 'decodeInconsistentUtf8']]];
                }
            }
        }

        // Latin-1 vs Windows-1252 confusion
        if (in_array('latin-1', $possible1byteEncodings, true)) {
            if (in_array('sloppy-windows-1252', $possible1byteEncodings, true)) {
                // In the intersection → probably legit text.
                return [$text, []];
            }

            // Has Latin-1 chars not in Windows-1252 (C1 controls) → reinterpret.
            $bytes = self::encodeToBytes($text, 'latin-1');
            if ($bytes !== null) {
                $fixed = SloppyCodecs::decode($bytes, 'windows-1252');
                if ($fixed !== $text) {
                    return [$fixed, [['encode', 'latin-1'], ['decode', 'windows-1252']]];
                }
            }
        }

        // Fix individual C1 control characters.
        if ($config->fixC1Controls && preg_match(CharData::C1_CONTROL_RE, $text)) {
            $fixed = Fixes::fixC1Controls($text);
            return [$fixed, [['transcode', 'fixC1Controls']]];
        }

        return [$text, []];
    }

    // -------------------------------------------------------------------------
    // Encoding helpers
    // -------------------------------------------------------------------------

    /**
     * Check whether a string can be represented in the given single-byte encoding.
     */
    private static function possibleEncodingCheck(string $text, string $encoding): bool
    {
        $encoding = strtolower($encoding);

        if ($encoding === 'ascii') {
            return (bool) preg_match('/^[\x00-\x7f]*$/u', $text);
        }

        // For the standard (non-sloppy) encodings we delegate to SloppyCodecs
        // since they share the same character repertoire.
        return SloppyCodecs::possibleEncoding($text, $encoding);
    }

    /**
     * Encode a UTF-8 string to raw bytes in the given encoding.
     * Returns null if the encoding is not supported.
     */
    private static function encodeToBytes(string $utf8, string $encoding): ?string
    {
        $enc = strtolower($encoding);

        if ($enc === 'latin-1' || $enc === 'iso-8859-1') {
            // Latin-1: codepoint == byte for 0x00-0xFF; drop anything else.
            $chunks = [];
            foreach (mb_str_split($utf8, 1, 'UTF-8') as $char) {
                $cp = mb_ord($char, 'UTF-8');
                if ($cp <= 0xFF) {
                    $chunks[] = chr($cp);
                }
            }
            return implode('', $chunks);
        }

        if (str_starts_with($enc, 'sloppy-') || str_starts_with($enc, 'sloppy_')) {
            $base = preg_replace('/^sloppy[-_]/', '', $enc);
            return SloppyCodecs::encode($utf8, $base);
        }

        if ($enc === 'iso-8859-2') {
            $result = @iconv('UTF-8', 'ISO-8859-2//IGNORE', $utf8);
            return $result !== false ? $result : null;
        }

        if ($enc === 'macroman') {
            $result = @iconv('UTF-8', 'MACINTOSH//IGNORE', $utf8);
            return $result !== false ? $result : null;
        }

        if ($enc === 'cp437') {
            $result = @iconv('UTF-8', 'CP437//IGNORE', $utf8);
            return $result !== false ? $result : null;
        }

        return null;
    }

    /**
     * Strictly decode a binary string as UTF-8.
     * Returns null if any byte is invalid UTF-8.
     */
    private static function strictUtf8Decode(string $bytes): ?string
    {
        // Check if the bytes are valid UTF-8.
        if (!mb_check_encoding($bytes, 'UTF-8')) {
            return null;
        }
        // In PHP, strings are already bytes, so "decoding" UTF-8 is a no-op.
        return $bytes;
    }

    // -------------------------------------------------------------------------
    // apply_plan (for roundtrip compatibility)
    // -------------------------------------------------------------------------

    /**
     * Apply a plan returned by fixAndExplain to transform text.
     *
     * Each step is [operation, parameter] where operation is one of:
     *   'encode', 'decode', 'transcode', 'apply', 'normalize'
     *
     * @param list<array{string,string}> $plan
     */
    public static function applyPlan(string $text, array $plan): string
    {
        $obj = $text;
        foreach ($plan as [$operation, $param]) {
            switch ($operation) {
                case 'encode':
                    $obj = self::encodeToBytes($obj, $param) ?? $obj;
                    break;
                case 'decode':
                    if ($param === 'utf-8-variants') {
                        $obj = Utf8Variants::decode($obj);
                    } else {
                        $obj = self::strictUtf8Decode($obj) ?? $obj;
                    }
                    break;
                case 'transcode':
                case 'apply':
                    $obj = match ($param) {
                        'restoreByteA0'         => Fixes::restoreByteA0($obj),
                        'replaceLossySequences' => Fixes::replaceLossySequences($obj),
                        'decodeInconsistentUtf8'=> Fixes::decodeInconsistentUtf8($obj),
                        'fixC1Controls'         => Fixes::fixC1Controls($obj),
                        'unescapeHtml'          => Fixes::unescapeHtml($obj),
                        'removeTerminalEscapes' => Fixes::removeTerminalEscapes($obj),
                        'fixLatinLigatures'     => Fixes::fixLatinLigatures($obj),
                        'fixCharacterWidth'     => Fixes::fixCharacterWidth($obj),
                        'uncurlQuotes'          => Fixes::uncurlQuotes($obj),
                        'fixLineBreaks'         => Fixes::fixLineBreaks($obj),
                        'fixSurrogates'         => Fixes::fixSurrogates($obj),
                        'removeControlChars'    => Fixes::removeControlChars($obj),
                        default => throw new \ValueError("Unknown function: $param"),
                    };
                    break;
                case 'normalize':
                    $normConst = match ($param) {
                        'NFC'  => \Normalizer::FORM_C,
                        'NFD'  => \Normalizer::FORM_D,
                        'NFKC' => \Normalizer::FORM_KC,
                        'NFKD' => \Normalizer::FORM_KD,
                        default => \Normalizer::FORM_C,
                    };
                    $obj = \Normalizer::normalize($obj, $normConst) ?: $obj;
                    break;
                default:
                    throw new \ValueError("Unknown plan step: $operation");
            }
        }
        return $obj;
    }
}
