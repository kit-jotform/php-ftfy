# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.1] - 2026-04-05

### Changed
- Set own version scheme (`1.1.1`) instead of mirroring Python ftfy version
- Fix PHPCS PSR-12 violations across all source files
- Add `squizlabs/php_codesniffer` as dev dependency

## [1.1.0] - 2026-04-04

### Added
- `Ftfy::needsFix()` fast dry-run method to check if text needs fixing without applying changes

### Changed
- Eliminate O(n²) string concatenation and cache regex patterns
- Reduce passes in `fixLineBreaks`, bulk-copy in `fixSurrogates`, hoist match
- Use `strtr`/bulk-copy and eliminate `mb_` overhead in hot paths
- Remove unnecessary comments across all library files

## [1.0.1] - 2026-03-26

### Added
- LICENSE file (MIT)
- PHPUnit configuration (`phpunit.xml`)
- Export-ignore rules in `.gitattributes` for Packagist distribution

## [1.0.0] - 2026-03-26

### Added
- Full PHP 8.1+ port of Python ftfy 6.3.1
- `Ftfy::fixText()` — fix all text issues (encoding, HTML entities, quotes, etc.)
- `Ftfy::fixEncoding()` — fix encoding issues only
- `Ftfy::fixAndExplain()` — fix text with explanation of changes applied
- `Ftfy::applyPlan()` — apply a transformation plan to text
- `TextFixerConfig` — immutable configuration with 16 toggleable fixers
- CLI script for command-line usage
- Sloppy Windows-125x codec support
- CESU-8 / Java modified UTF-8 decoder
- Mojibake detection via badness heuristic
- 33 PHPUnit tests
