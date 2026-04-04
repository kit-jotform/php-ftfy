<?php

declare(strict_types=1);

namespace Ftfy;

/**
 * Configuration options for ftfy text fixing.
 *
 * @param string|bool $unescapeHtml  "auto" = unescape unless a literal '<' is found,
 *                                   true = always unescape, false = never.
 */
final class TextFixerConfig
{
    public function __construct(
        public readonly string|bool $unescapeHtml = 'auto',
        public readonly bool $removeTerminalEscapes = true,
        public readonly bool $fixEncoding = true,
        public readonly bool $restoreByteA0 = true,
        public readonly bool $replaceLossySequences = true,
        public readonly bool $decodeInconsistentUtf8 = true,
        public readonly bool $fixC1Controls = true,
        public readonly bool $fixLatinLigatures = true,
        public readonly bool $fixCharacterWidth = true,
        public readonly bool $uncurlQuotes = true,
        public readonly bool $fixLineBreaks = true,
        public readonly bool $fixSurrogates = true,
        public readonly bool $removeControlChars = true,
        public readonly string|null $normalization = 'NFC',
        public readonly int $maxDecodeLength = 1_000_000,
        public readonly bool $explain = true,
    ) {
    }

    public function with(mixed ...$overrides): self
    {
        return new self(
            unescapeHtml:            $overrides['unescapeHtml']            ?? $this->unescapeHtml,
            removeTerminalEscapes:   $overrides['removeTerminalEscapes']   ?? $this->removeTerminalEscapes,
            fixEncoding:             $overrides['fixEncoding']             ?? $this->fixEncoding,
            restoreByteA0:           $overrides['restoreByteA0']           ?? $this->restoreByteA0,
            replaceLossySequences:   $overrides['replaceLossySequences']   ?? $this->replaceLossySequences,
            decodeInconsistentUtf8:  $overrides['decodeInconsistentUtf8']  ?? $this->decodeInconsistentUtf8,
            fixC1Controls:           $overrides['fixC1Controls']           ?? $this->fixC1Controls,
            fixLatinLigatures:       $overrides['fixLatinLigatures']       ?? $this->fixLatinLigatures,
            fixCharacterWidth:       $overrides['fixCharacterWidth']       ?? $this->fixCharacterWidth,
            uncurlQuotes:            $overrides['uncurlQuotes']            ?? $this->uncurlQuotes,
            fixLineBreaks:           $overrides['fixLineBreaks']           ?? $this->fixLineBreaks,
            fixSurrogates:           $overrides['fixSurrogates']           ?? $this->fixSurrogates,
            removeControlChars:      $overrides['removeControlChars']      ?? $this->removeControlChars,
            normalization:           $overrides['normalization']           ?? $this->normalization,
            maxDecodeLength:         $overrides['maxDecodeLength']         ?? $this->maxDecodeLength,
            explain:                 $overrides['explain']                 ?? $this->explain,
        );
    }
}
