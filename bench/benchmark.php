<?php

/**
 * Benchmark: compare Ftfy::needsFix vs Ftfy::fixText, single-string vs chunked.
 *
 * Usage:
 *   php bench/benchmark.php [--iterations=N] [--file=path/to/file.txt]
 *
 * Outputs a Markdown-style table with mean ms and relative speed.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Ftfy\Ftfy;
use Ftfy\Badness;
use Ftfy\TextFixerConfig;

// ---------------------------------------------------------------------------
// CLI args
// ---------------------------------------------------------------------------
$opts = getopt('', ['iterations:', 'file:']);
$iterations = (int)($opts['iterations'] ?? 500);
$inputFile  = $opts['file'] ?? dirname(__DIR__) . '/broken.txt';

if (!file_exists($inputFile)) {
    fwrite(STDERR, "Input file not found: $inputFile\n");
    exit(1);
}

$singleLine = trim(file_get_contents($inputFile));

// Build a large (~64 KB) payload by repeating the file content.
$targetBytes = 65536;
$repeated = $singleLine;
while (strlen($repeated) < $targetBytes) {
    $repeated .= "\n" . $singleLine;
}
$largeText = $repeated;

echo "php-ftfy benchmark\n";
echo "==================\n";
echo sprintf("PHP:        %s\n", PHP_VERSION);
echo sprintf("Iterations: %d per scenario\n", $iterations);
echo sprintf("Small input: %d bytes\n", strlen($singleLine));
echo sprintf("Large input: %d bytes\n", strlen($largeText));
echo "\n";

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Run $fn $n times and return [mean_ms, min_ms, max_ms].
 *
 * @param callable $fn
 * @return array{float, float, float}
 */
function bench(callable $fn, int $n): array
{
    $times = [];
    for ($i = 0; $i < $n; $i++) {
        $t0 = hrtime(true);
        $fn();
        $times[] = (hrtime(true) - $t0) / 1e6; // ns → ms
    }
    sort($times);
    $mean = array_sum($times) / $n;
    return [$mean, $times[0], $times[(int)($n * 0.99)]]; // mean, min, p99
}

/**
 * Simulate "chunkless" isBad by calling the pattern directly on the full string
 * (bypasses the automatic chunking in Badness::isBad).
 */
function isBadNoChunk(string $text): bool
{
    try {
        return (bool) preg_match(Badness::getPattern(), $text);
    } catch (\ValueError) {
        return false;
    }
}

// ---------------------------------------------------------------------------
// Scenarios
// ---------------------------------------------------------------------------

$scenarios = [
    // --- small input ---
    'needsFix (small)'        => ['input' => $singleLine, 'fn' => fn($t) => Ftfy::needsFix($t)],
    'fixText (small)'         => ['input' => $singleLine, 'fn' => fn($t) => Ftfy::fixText($t)],
    'isBad chunked (small)'   => ['input' => $singleLine, 'fn' => fn($t) => Badness::isBad($t)],
    'isBad no-chunk (small)'  => ['input' => $singleLine, 'fn' => fn($t) => isBadNoChunk($t)],

    // --- large input ---
    'needsFix (large)'        => ['input' => $largeText, 'fn' => fn($t) => Ftfy::needsFix($t)],
    'fixText (large)'         => ['input' => $largeText, 'fn' => fn($t) => Ftfy::fixText($t)],
    'isBad chunked (large)'   => ['input' => $largeText, 'fn' => fn($t) => Badness::isBad($t)],
    'isBad no-chunk (large)'  => ['input' => $largeText, 'fn' => fn($t) => isBadNoChunk($t)],
];

// ---------------------------------------------------------------------------
// Run & collect
// ---------------------------------------------------------------------------

/** @var array<string, array{float, float, float}> $results */
$results = [];

foreach ($scenarios as $label => ['input' => $input, 'fn' => $fn]) {
    echo "Running: $label ... ";
    flush();
    [$mean, $min, $p99] = bench(fn() => $fn($input), $iterations);
    $results[$label] = [$mean, $min, $p99];
    echo sprintf("mean=%.3f ms\n", $mean);
}

// ---------------------------------------------------------------------------
// Table
// ---------------------------------------------------------------------------

echo "\n";
echo "Results\n";
echo "-------\n";
$header = sprintf(
    "%-30s %10s %10s %10s %10s",
    'Scenario', 'Mean (ms)', 'Min (ms)', 'P99 (ms)', 'vs needsFix'
);
echo $header . "\n";
echo str_repeat('-', strlen($header)) . "\n";

// baselines per size
$baseSmall = $results['needsFix (small)'][0];
$baseLarge = $results['needsFix (large)'][0];

foreach ($results as $label => [$mean, $min, $p99]) {
    $base = str_contains($label, 'large') ? $baseLarge : $baseSmall;
    $ratio = $base > 0 ? $mean / $base : 0.0;
    echo sprintf(
        "%-30s %10.3f %10.3f %10.3f %10.2fx\n",
        $label, $mean, $min, $p99, $ratio
    );
}

// ---------------------------------------------------------------------------
// needsFix gate savings analysis
// ---------------------------------------------------------------------------

echo "\n";
echo "Gate analysis (how much fixText() work needsFix() saves)\n";
echo "--------------------------------------------------------\n";

foreach (['small', 'large'] as $size) {
    $nfMean  = $results["needsFix ($size)"][0];
    $fixMean = $results["fixText ($size)"][0];
    $saving  = $fixMean > 0 ? (1 - $nfMean / $fixMean) * 100 : 0.0;
    echo sprintf(
        "%-6s: needsFix=%.3f ms, fixText=%.3f ms  =>  gate saves ~%.0f%% of work on clean text\n",
        $size, $nfMean, $fixMean, $saving
    );
}
