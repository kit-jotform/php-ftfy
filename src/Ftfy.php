<?php

declare(strict_types=1);

namespace Ftfy;

use Ftfy\Codecs\SloppyCodecs;
use Ftfy\Codecs\Utf8Variants;

/** Main entry point. Based on Python ftfy 6.3.1. */
final class Ftfy
{
    public const VERSION = '1.1.1';

    /**
     * Fix Unicode problems in text, returning the corrected string.
     *
     * Processes text in line-length segments (split on "\n") to bound
     * worst-case runtime, exactly as the Python version does.
     */
    public static function fixText(string $text, ?TextFixerConfig $config = null): string
    {
        $config ??= new TextFixerConfig(explain: false);
        $config = $config->with(explain: false);

        // "\n" is a single byte in UTF-8, so byte-level strpos/substr is safe
        // and much faster than mb_strpos on large inputs.
        $out = [];
        $pos = 0;
        $len = strlen($text);

        while ($pos < $len) {
            $nlPos = strpos($text, "\n", $pos);
            $textbreak = ($nlPos === false) ? $len : $nlPos + 1;

            // maxDecodeLength is a character count; use mb_substr in the rare
            // case a segment might exceed it (worst-case 4 bytes per char).
            if (($textbreak - $pos) > $config->maxDecodeLength * 4) {
                // phpcs:ignore Generic.Files.LineLength
                $segment = mb_substr($text, mb_strlen(substr($text, 0, $pos), 'UTF-8'), $config->maxDecodeLength, 'UTF-8');
                $pos += strlen($segment);
            } else {
                $segment = substr($text, $pos, $textbreak - $pos);
                $pos = $textbreak;
            }

            if ($config->unescapeHtml === 'auto' && str_contains($segment, '<')) {
                $config = $config->with(unescapeHtml: false);
            }

            [$fixed] = self::fixAndExplain($segment, $config);
            $out[] = $fixed;
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

            $text = self::tryFix('unescapeHtml', $text, $config, $steps);

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

    public static function fixEncoding(string $text, ?TextFixerConfig $config = null): string
    {
        $config ??= new TextFixerConfig(explain: false);

        if (!$config->fixEncoding) {
            return $text;
        }

        while (true) {
            $prev = $text;
            [$text] = self::fixEncodingOneStep($text, $config);
            if ($text === $prev) {
                return $text;
            }
        }
    }

    /**
     * Fast dry-run: does the text need fixing?
     *
     * Checks byte/character patterns each fixer targets without performing
     * corrections. Short-circuits on the first match. Use as a gate before
     * fixText() on hot paths.
     */
    public static function needsFix(string $text, ?TextFixerConfig $config = null): bool
    {
        if ($text === '') {
            return false;
        }

        if (!mb_check_encoding($text, 'UTF-8')) {
            return true;
        }

        if (!preg_match('/[^\x09\x0A\x20-\x25\x27-\x7E]|&/', $text)) {
            return false;
        }

        $config ??= new TextFixerConfig(explain: false);

        if (
            $config->fixLineBreaks
            && (str_contains($text, "\r")
                || str_contains($text, "\u{2028}")
                || str_contains($text, "\u{2029}")
                || str_contains($text, "\u{0085}"))
        ) {
            return true;
        }

        if ($config->removeTerminalEscapes && str_contains($text, "\033")) {
            return true;
        }

        if (
            $config->unescapeHtml !== false
            && ($config->unescapeHtml !== 'auto' || !str_contains($text, '<'))
            && preg_match(CharData::HTML_ENTITY_RE, $text)
        ) {
            return true;
        }

        $charClasses = [];
        if ($config->fixC1Controls) {
            $charClasses[] = '\x{0080}-\x{009F}';
        }
        if ($config->uncurlQuotes) {
            $charClasses[] = '\x{02BC}\x{2018}-\x{201F}';
        }
        if ($config->fixLatinLigatures) {
            $charClasses[] = '\x{FB00}-\x{FB06}\x{0132}\x{0133}\x{0149}\x{01C4}-\x{01CC}\x{01F1}-\x{01F3}';
        }
        if ($config->fixCharacterWidth) {
            $charClasses[] = '\x{3000}\x{FF01}-\x{FFEF}';
        }
        if ($config->removeControlChars) {
            // phpcs:ignore Generic.Files.LineLength
            $charClasses[] = '\x{0000}-\x{0008}\x{000B}\x{000E}-\x{001F}\x{007F}\x{206A}-\x{206F}\x{FEFF}\x{FFF9}-\x{FFFC}';
        }
        if ($charClasses !== []) {
            $charClassPattern = '/[' . implode('', $charClasses) . ']/u';
            $len = strlen($text);
            if ($len > 8192) {
                $chunkSize = 8192;
                $overlap   = 16;
                for ($offset = 0; $offset < $len; $offset += $chunkSize) {
                    $chunk = substr($text, $offset, $chunkSize + $overlap);
                    try {
                        if (preg_match($charClassPattern, $chunk)) {
                            return true;
                        }
                    } catch (\ValueError) {
                        // skip chunk
                    }
                }
            } else {
                try {
                    if (preg_match($charClassPattern, $text)) {
                        return true;
                    }
                } catch (\ValueError) {
                    // PCRE limit hit — continue to next checks
                }
            }
        }

        if (
            $config->normalization !== null
            && !\Normalizer::isNormalized($text, match ($config->normalization) {
                'NFC'  => \Normalizer::FORM_C,
                'NFD'  => \Normalizer::FORM_D,
                'NFKC' => \Normalizer::FORM_KC,
                'NFKD' => \Normalizer::FORM_KD,
                default => \Normalizer::FORM_C,
            })
        ) {
            return true;
        }

        if ($config->fixEncoding && !SloppyCodecs::possibleEncoding($text, 'ascii')) {
            try {
                $hasC1OrBadSeq = (bool) preg_match('/[\x{0080}-\x{009F}]|[\x{00C0}-\x{00DF}][\x{0080}-\x{00BF}]/u', $text);
            } catch (\ValueError) {
                $hasC1OrBadSeq = false;
            }
            if ($hasC1OrBadSeq && Badness::isBad($text)) {
                return true;
            }
        }

        return false;
    }

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

    /** @param list<array{string,string}>|null $steps */
    private static function tryFix(
        string $fixerName,
        string $text,
        TextFixerConfig $config,
        ?array &$steps
    ): string {
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

    /** @return array{string, list<array{string,string}>|null} */
    private static function fixEncodingOneStep(string $text, TextFixerConfig $config): array
    {
        if ($text === '') {
            return [$text, []];
        }

        if (SloppyCodecs::possibleEncoding($text, 'ascii')) {
            return [$text, []];
        }

        if (!Badness::isBad($text)) {
            return [$text, []];
        }

        $possible1byteEncodings = [];

        foreach (CharData::CHARMAP_ENCODINGS as $encoding) {
            if (!self::possibleEncodingCheck($text, $encoding)) {
                continue;
            }

            $possible1byteEncodings[] = $encoding;

            $encodedBytes = self::encodeToBytes($text, $encoding);
            $encodeStep = ['encode', $encoding];
            $transcodeSteps = [];

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
                        continue;
                    }
                }

                return [$fixed, $steps];
            } catch (\ValueError) {
                // Decoding failed; try next encoding.
            }
        }

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
                return [$text, []];
            }

            // Has Latin-1 C1 chars not in Windows-1252 → reinterpret as Windows-1252.
            $bytes = self::encodeToBytes($text, 'latin-1');
            if ($bytes !== null) {
                $fixed = SloppyCodecs::decode($bytes, 'windows-1252');
                if ($fixed !== $text) {
                    return [$fixed, [['encode', 'latin-1'], ['decode', 'windows-1252']]];
                }
            }
        }

        if ($config->fixC1Controls && preg_match(CharData::C1_CONTROL_RE, $text)) {
            $fixed = Fixes::fixC1Controls($text);
            return [$fixed, [['transcode', 'fixC1Controls']]];
        }

        return [$text, []];
    }

    private static function possibleEncodingCheck(string $text, string $encoding): bool
    {
        if (strtolower($encoding) === 'ascii') {
            return (bool) preg_match('/^[\x00-\x7f]*$/u', $text);
        }

        return SloppyCodecs::possibleEncoding($text, $encoding);
    }

    private static function encodeToBytes(string $utf8, string $encoding): ?string
    {
        $enc = strtolower($encoding);

        if ($enc === 'latin-1' || $enc === 'iso-8859-1') {
            // Latin-1: codepoint == byte for 0x00-0xFF.
            // In UTF-8, U+0080-U+00FF are two-byte sequences C2/C3 xx.
            // Codepoints above U+00FF (lead byte >= 0xC4) are dropped.
            $len = strlen($utf8);
            $chunks = [];
            $copyFrom = 0;
            $i = 0;
            while ($i < $len) {
                $b = ord($utf8[$i]);
                if ($b < 0x80) {
                    $i++;
                    continue;
                }
                if ($b === 0xC2 || $b === 0xC3) {
                    if ($i > $copyFrom) {
                        $chunks[] = substr($utf8, $copyFrom, $i - $copyFrom);
                    }
                    $cp = (($b & 0x1F) << 6) | (ord($utf8[$i + 1]) & 0x3F);
                    $chunks[] = chr($cp);
                    $i += 2;
                    $copyFrom = $i;
                    continue;
                }
                // Lead byte >= 0xC4: codepoint > U+00FF — drop the sequence.
                if ($i > $copyFrom) {
                    $chunks[] = substr($utf8, $copyFrom, $i - $copyFrom);
                }
                if (($b & 0xE0) === 0xC0) {
                    $i += 2;
                } elseif (($b & 0xF0) === 0xE0) {
                    $i += 3;
                } elseif (($b & 0xF8) === 0xF0) {
                    $i += 4;
                } else {
                    $i++;
                }
                $copyFrom = $i;
            }
            if ($copyFrom === 0) {
                return $utf8;
            }
            if ($copyFrom < $len) {
                $chunks[] = substr($utf8, $copyFrom);
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

    private static function strictUtf8Decode(string $bytes): ?string
    {
        if (!mb_check_encoding($bytes, 'UTF-8')) {
            return null;
        }
        return $bytes;
    }
}
