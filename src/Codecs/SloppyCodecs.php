<?php

declare(strict_types=1);

namespace Ftfy\Codecs;

/**
 * "Sloppy" single-byte encodings that fill unmapped bytes with their Latin-1
 * equivalents (same as what HTML5 browsers do).
 *
 * The decode tables below were generated from Python's codec data:
 * for each encoding, bytes 0x80-0xFF are listed where they differ from Latin-1.
 * Byte 0x1A is always mapped to U+FFFD (replacement character) for ftfy's use.
 */
final class SloppyCodecs
{
    // Diff tables: [byte => unicode codepoint] for bytes 0x80-0xFF that differ from Latin-1.
    // Byte 0x1A => 0xFFFD is applied on top of each table.
    private const DIFF_TABLES = [
        'windows-1250' => [
            128 => 8364, 130 => 8218, 132 => 8222, 133 => 8230, 134 => 8224,
            135 => 8225, 137 => 8240, 138 =>  352, 139 => 8249, 140 =>  346,
            141 =>  356, 142 =>  381, 143 =>  377, 145 => 8216, 146 => 8217,
            147 => 8220, 148 => 8221, 149 => 8226, 150 => 8211, 151 => 8212,
            153 => 8482, 154 =>  353, 155 => 8250, 156 =>  347, 157 =>  357,
            158 =>  382, 159 =>  378, 161 =>  711, 162 =>  728, 163 =>  321,
            165 =>  260, 170 =>  350, 175 =>  379, 178 =>  731, 179 =>  322,
            185 =>  261, 186 =>  351, 188 =>  317, 189 =>  733, 190 =>  318,
            191 =>  380, 192 =>  340, 195 =>  258, 197 =>  313, 198 =>  262,
            200 =>  268, 202 =>  280, 204 =>  282, 207 =>  270, 208 =>  272,
            209 =>  323, 210 =>  327, 213 =>  336, 216 =>  344, 217 =>  366,
            219 =>  368, 222 =>  354, 224 =>  341, 227 =>  259, 229 =>  314,
            230 =>  263, 232 =>  269, 234 =>  281, 236 =>  283, 239 =>  271,
            240 =>  273, 241 =>  324, 242 =>  328, 245 =>  337, 248 =>  345,
            249 =>  367, 251 =>  369, 254 =>  355, 255 =>  729,
        ],
        'windows-1251' => [
            128 => 1026, 129 => 1027, 130 => 8218, 131 => 1107, 132 => 8222,
            133 => 8230, 134 => 8224, 135 => 8225, 136 => 8364, 137 => 8240,
            138 => 1033, 139 => 8249, 140 => 1034, 141 => 1036, 142 => 1035,
            143 => 1039, 144 => 1106, 145 => 8216, 146 => 8217, 147 => 8220,
            148 => 8221, 149 => 8226, 150 => 8211, 151 => 8212, 153 => 8482,
            154 => 1113, 155 => 8250, 156 => 1114, 157 => 1116, 158 => 1115,
            159 => 1119, 161 => 1038, 162 => 1118, 163 => 1032, 165 => 1168,
            168 => 1025, 170 => 1028, 175 => 1031, 178 => 1030, 179 => 1110,
            180 => 1169, 184 => 1105, 185 => 8470, 186 => 1108, 188 => 1112,
            189 => 1029, 190 => 1109, 191 => 1111, 192 => 1040, 193 => 1041,
            194 => 1042, 195 => 1043, 196 => 1044, 197 => 1045, 198 => 1046,
            199 => 1047, 200 => 1048, 201 => 1049, 202 => 1050, 203 => 1051,
            204 => 1052, 205 => 1053, 206 => 1054, 207 => 1055, 208 => 1056,
            209 => 1057, 210 => 1058, 211 => 1059, 212 => 1060, 213 => 1061,
            214 => 1062, 215 => 1063, 216 => 1064, 217 => 1065, 218 => 1066,
            219 => 1067, 220 => 1068, 221 => 1069, 222 => 1070, 223 => 1071,
            224 => 1072, 225 => 1073, 226 => 1074, 227 => 1075, 228 => 1076,
            229 => 1077, 230 => 1078, 231 => 1079, 232 => 1080, 233 => 1081,
            234 => 1082, 235 => 1083, 236 => 1084, 237 => 1085, 238 => 1086,
            239 => 1087, 240 => 1088, 241 => 1089, 242 => 1090, 243 => 1091,
            244 => 1092, 245 => 1093, 246 => 1094, 247 => 1095, 248 => 1096,
            249 => 1097, 250 => 1098, 251 => 1099, 252 => 1100, 253 => 1101,
            254 => 1102, 255 => 1103,
        ],
        'windows-1252' => [
            128 => 8364, 130 => 8218, 131 =>  402, 132 => 8222, 133 => 8230,
            134 => 8224, 135 => 8225, 136 =>  710, 137 => 8240, 138 =>  352,
            139 => 8249, 140 =>  338, 142 =>  381, 145 => 8216, 146 => 8217,
            147 => 8220, 148 => 8221, 149 => 8226, 150 => 8211, 151 => 8212,
            152 =>  732, 153 => 8482, 154 =>  353, 155 => 8250, 156 =>  339,
            158 =>  382, 159 =>  376,
        ],
        'windows-1253' => [
            128 => 8364, 130 => 8218, 131 =>  402, 132 => 8222, 133 => 8230,
            134 => 8224, 135 => 8225, 137 => 8240, 139 => 8249, 145 => 8216,
            146 => 8217, 147 => 8220, 148 => 8221, 149 => 8226, 150 => 8211,
            151 => 8212, 153 => 8482, 155 => 8250, 161 =>  901, 162 =>  902,
            175 => 8213, 180 =>  900, 184 =>  904, 185 =>  905, 186 =>  906,
            188 =>  908, 190 =>  910, 191 =>  911, 192 =>  912, 193 =>  913,
            194 =>  914, 195 =>  915, 196 =>  916, 197 =>  917, 198 =>  918,
            199 =>  919, 200 =>  920, 201 =>  921, 202 =>  922, 203 =>  923,
            204 =>  924, 205 =>  925, 206 =>  926, 207 =>  927, 208 =>  928,
            209 =>  929, 211 =>  931, 212 =>  932, 213 =>  933, 214 =>  934,
            215 =>  935, 216 =>  936, 217 =>  937, 218 =>  938, 219 =>  939,
            220 =>  940, 221 =>  941, 222 =>  942, 223 =>  943, 224 =>  944,
            225 =>  945, 226 =>  946, 227 =>  947, 228 =>  948, 229 =>  949,
            230 =>  950, 231 =>  951, 232 =>  952, 233 =>  953, 234 =>  954,
            235 =>  955, 236 =>  956, 237 =>  957, 238 =>  958, 239 =>  959,
            240 =>  960, 241 =>  961, 242 =>  962, 243 =>  963, 244 =>  964,
            245 =>  965, 246 =>  966, 247 =>  967, 248 =>  968, 249 =>  969,
            250 =>  970, 251 =>  971, 252 =>  972, 253 =>  973, 254 =>  974,
        ],
        'windows-1254' => [
            128 => 8364, 130 => 8218, 131 =>  402, 132 => 8222, 133 => 8230,
            134 => 8224, 135 => 8225, 136 =>  710, 137 => 8240, 138 =>  352,
            139 => 8249, 140 =>  338, 145 => 8216, 146 => 8217, 147 => 8220,
            148 => 8221, 149 => 8226, 150 => 8211, 151 => 8212, 152 =>  732,
            153 => 8482, 154 =>  353, 155 => 8250, 156 =>  339, 159 =>  376,
            208 =>  286, 221 =>  304, 222 =>  350, 240 =>  287, 253 =>  305,
            254 =>  351,
        ],
        'windows-1257' => [
            128 => 8364, 130 => 8218, 132 => 8222, 133 => 8230, 134 => 8224,
            135 => 8225, 137 => 8240, 139 => 8249, 141 =>  168, 142 =>  711,
            143 =>  184, 145 => 8216, 146 => 8217, 147 => 8220, 148 => 8221,
            149 => 8226, 150 => 8211, 151 => 8212, 153 => 8482, 155 => 8250,
            157 =>  175, 158 =>  731, 168 =>  216, 170 =>  342, 175 =>  198,
            184 =>  248, 186 =>  343, 191 =>  230, 192 =>  260, 193 =>  302,
            194 =>  256, 195 =>  262, 198 =>  280, 199 =>  274, 200 =>  268,
            202 =>  377, 203 =>  278, 204 =>  290, 205 =>  310, 206 =>  298,
            207 =>  315, 208 =>  352, 209 =>  323, 210 =>  325, 212 =>  332,
            216 =>  370, 217 =>  321, 218 =>  346, 219 =>  362, 221 =>  379,
            222 =>  381, 224 =>  261, 225 =>  303, 226 =>  257, 227 =>  263,
            230 =>  281, 231 =>  275, 232 =>  269, 234 =>  378, 235 =>  279,
            236 =>  291, 237 =>  311, 238 =>  299, 239 =>  316, 240 =>  353,
            241 =>  324, 242 =>  326, 244 =>  333, 248 =>  371, 249 =>  322,
            250 =>  347, 251 =>  363, 253 =>  380, 254 =>  382, 255 =>  729,
        ],
    ];

    /** @var array<string, string[]> Runtime decode tables (byte index → UTF-8 char) */
    private static array $decodeTables = [];

    /** @var array<string, array<int,int>> Runtime encode tables (codepoint → byte) */
    private static array $encodeTables = [];

    /**
     * Return the full 256-entry decode table for an encoding as an array of
     * UTF-8 strings indexed by byte value.
     *
     * @return string[]
     */
    public static function getDecodeTable(string $encoding): array
    {
        $key = self::normalise($encoding);
        if (isset(self::$decodeTables[$key])) {
            return self::$decodeTables[$key];
        }

        // Start with Latin-1 identity mapping (codepoint == byte value for 0x80-0xFF).
        $table = [];
        for ($i = 0; $i < 256; $i++) {
            $table[$i] = mb_chr($i, 'UTF-8');
        }

        // Byte 0x1A always maps to U+FFFD.
        $table[0x1A] = "\u{FFFD}";

        // Apply encoding-specific overrides.
        $baseKey = self::resolveBase($key);
        if ($baseKey !== null && isset(self::DIFF_TABLES[$baseKey])) {
            foreach (self::DIFF_TABLES[$baseKey] as $byte => $codepoint) {
                $table[$byte] = mb_chr($codepoint, 'UTF-8');
            }
        }

        self::$decodeTables[$key] = $table;
        return $table;
    }

    /**
     * Decode a raw binary string using a sloppy single-byte encoding.
     */
    public static function decode(string $bytes, string $encoding): string
    {
        $table = self::getDecodeTable($encoding);
        $chunks = [];
        $len = strlen($bytes);
        for ($i = 0; $i < $len; $i++) {
            $chunks[] = $table[ord($bytes[$i])];
        }
        return implode('', $chunks);
    }

    /**
     * Encode a UTF-8 string to raw bytes using a sloppy single-byte encoding.
     * Characters that cannot be encoded are silently dropped (like //IGNORE).
     */
    public static function encode(string $utf8, string $encoding): string
    {
        $key = self::normalise($encoding);
        if (!isset(self::$encodeTables[$key])) {
            $decodeTable = self::getDecodeTable($encoding);
            $enc = [];
            foreach ($decodeTable as $byte => $char) {
                $cp = mb_ord($char, 'UTF-8');
                // Last write wins — consistent with Python's charmap_build
                $enc[$cp] = $byte;
            }
            self::$encodeTables[$key] = $enc;
        }
        $encTable = self::$encodeTables[$key];

        $chunks = [];
        $chars = mb_str_split($utf8, 1, 'UTF-8');
        foreach ($chars as $char) {
            $cp = mb_ord($char, 'UTF-8');
            if ($cp < 0x80) {
                $chunks[] = chr($cp);
            } elseif (isset($encTable[$cp])) {
                $chunks[] = chr($encTable[$cp]);
            }
            // else: drop unencodable character
        }
        return implode('', $chunks);
    }

    /**
     * Check whether a UTF-8 string can be represented in the given encoding.
     * This mirrors Python's `possible_encoding(text, encoding)`.
     */
    public static function possibleEncoding(string $utf8, string $encoding): bool
    {
        if ($encoding === 'ascii') {
            return (bool) preg_match('/^[\x00-\x7f]*$/u', $utf8);
        }

        $key = self::normalise($encoding);
        $baseKey = self::resolveBase($key);
        if ($baseKey === null) {
            return false;
        }

        // Build a regex matching every character that can appear in this encoding.
        // We cache this per encoding.
        static $regexCache = [];
        if (!isset($regexCache[$key])) {
            $decodeTable = self::getDecodeTable($encoding);
            $chars = array_unique(array_values($decodeTable));
            $pattern = self::buildCharsetRegex($chars);
            $regexCache[$key] = $pattern;
        }

        return (bool) preg_match($regexCache[$key], $utf8);
    }

    // -------------------------------------------------------------------------

    private static function normalise(string $encoding): string
    {
        return strtolower(str_replace(['-', ' '], '_', $encoding));
    }

    private static function resolveBase(string $normKey): ?string
    {
        // Accept both "sloppy_windows_1252" and "windows_1252" keys.
        $stripped = preg_replace('/^sloppy_/', '', $normKey) ?? $normKey;
        // Re-hyphenate for our table keys.
        $hyphen = str_replace('_', '-', $stripped);
        if (isset(self::DIFF_TABLES[$hyphen])) {
            return $hyphen;
        }
        // Also accept iso_8859_X → iso-8859-X
        $isoHyphen = preg_replace('/iso_8859_(\d+)/', 'iso-8859-$1', $hyphen) ?? $hyphen;
        if (isset(self::DIFF_TABLES[$isoHyphen])) {
            return $isoHyphen;
        }
        return null;
    }

    /**
     * Build a PCRE character class pattern that matches exactly the given chars.
     *
     * @param string[] $chars
     */
    private static function buildCharsetRegex(array $chars): string
    {
        $codepoints = array_map(fn(string $c) => mb_ord($c, 'UTF-8'), $chars);
        sort($codepoints);

        // Also include ASCII 0x00-0x7F (always encodable in any single-byte enc)
        $pieces = ['\x00-\x7f'];

        foreach ($codepoints as $cp) {
            if ($cp >= 0x80) {
                $pieces[] = sprintf('\x{%04X}', $cp);
            }
        }

        return '/^[' . implode('', $pieces) . ']*$/u';
    }
}
