# php-ftfy: fixes text for you

A PHP 8.1+ port of the [Python ftfy library](https://github.com/rspeer/python-ftfy) (version 6.3.1) by Robyn Speer.

```php
use Ftfy\Ftfy;

echo Ftfy::fixText("(à¸‡'âŒ£')à¸‡");
// (ง'⌣')ง
```

## What it does

ftfy fixes mojibake — text that was encoded in UTF-8 but decoded as something else (Windows-1252, Latin-1, etc.), producing garbled characters.

```php
use Ftfy\Ftfy;

// Fix common mojibake
Ftfy::fixText('âœ" No problems');
// ✔ No problems

// Fix multiple layers of mojibake
Ftfy::fixText('The Mona Lisa doesnÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢t have eyebrows.');
// "The Mona Lisa doesn't have eyebrows."

// Fix HTML entities outside of HTML
Ftfy::fixText('P&EACUTE;REZ');
// PÉREZ

// Correctly-decoded text is left unchanged
Ftfy::fixText('IL Y MARQUÉ…');
// IL Y MARQUÉ…
```

## Installing

```bash
composer require php-ftfy/ftfy
```

**Requirements:** PHP >= 8.1, `ext-mbstring`, `ext-intl`

## Usage

### `Ftfy::fixText(string $text, ?TextFixerConfig $config = null): string`

Fix all encoding issues in a string.

```php
use Ftfy\Ftfy;

$fixed = Ftfy::fixText('Ã\xa0 perturber la rÃ©flexion');
// à perturber la réflexion
```

### `Ftfy::fixEncoding(string $text): string`

Fix only encoding/mojibake issues, without applying other text fixes.

```php
$fixed = Ftfy::fixEncoding("l'humanitÃ©");
// l'humanité
```

### `Ftfy::fixAndExplain(string $text, ?TextFixerConfig $config = null): array`

Returns `['text' => string, 'explanation' => array]` with the fixed text and a list of changes made.

```php
[$fixed, $explanation] = array_values(Ftfy::fixAndExplain('âœ" No problems'));
// $fixed      => '✔ No problems'
// $explanation => [['name' => 'fix_encoding', 'cost' => 1, ...]]
```

### Configuration

```php
use Ftfy\Ftfy;
use Ftfy\TextFixerConfig;

$config = new TextFixerConfig(
    fixEntities: true,       // decode HTML entities
    fixEncoding: true,       // fix mojibake
    fixSurrogates: true,     // fix surrogate characters
    fixLineBreaks: false,    // normalize line breaks
    fixLatin: false,         // fix Latin-1 lookalikes
    fixCharWidths: false,    // normalize character widths
    uncurlQuotes: true,      // straighten curly quotes
    removeTerminalEscapes: true,
    maxDecodeLength: 1_000_000,
);

$fixed = Ftfy::fixText($garbled, $config);
```

Use `$config->with(fixEntities: false)` to produce a modified copy.

## Running tests

```bash
composer install
vendor/bin/phpunit tests/
```

## Credits

- Original Python library: [ftfy](https://github.com/rspeer/python-ftfy) by [Robyn Speer](https://github.com/rspeer), licensed under Apache 2.0
- PHP port licensed under MIT
