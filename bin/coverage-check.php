<?php
/**
 * Fail if Clover coverage for all of src/ (including views) is below thresholds.
 *
 * Usage: php bin/coverage-check.php [--min-lines=98] [--min-methods=98] [path/to/clover.xml]
 */
$root = dirname(__DIR__);
$minLines = 98.0;
$minMethods = 98.0;
$clover = $root . '/coverage/clover.xml';

for ($i = 1; $i < $argc; $i++) {
    if (strpos($argv[$i], '--min-lines=') === 0) {
        $minLines = (float) substr($argv[$i], 12);
        continue;
    }
    if (strpos($argv[$i], '--min-methods=') === 0) {
        $minMethods = (float) substr($argv[$i], 14);
        continue;
    }
    if ($argv[$i] !== '--') {
        $clover = $argv[$i];
    }
}

if (!is_file($clover)) {
    fwrite(STDERR, "Missing Clover report: $clover\nRun: composer test-all\n");
    exit(1);
}

$x = simplexml_load_file($clover);
$methods = 0;
$methodsHit = 0;
$statements = 0;
$statementsHit = 0;

foreach ($x->xpath('//file') as $file) {
    foreach ($file->line as $line) {
        $type = (string) $line['type'];
        $count = (int) $line['count'];
        if ($type === 'method') {
            $methods++;
            if ($count > 0) {
                $methodsHit++;
            }
        } elseif ($type === 'stmt') {
            $statements++;
            if ($count > 0) {
                $statementsHit++;
            }
        }
    }
}

if ($methods === 0 || $statements === 0) {
    fwrite(STDERR, "No class metrics in Clover (unexpected).\n");
    exit(1);
}

$linePct = 100 * $statementsHit / $statements;
$methodPct = 100 * $methodsHit / $methods;

printf(
    "Coverage (src/): lines %.2f%% (%d/%d), methods %.2f%% (%d/%d)\n",
    $linePct,
    $statementsHit,
    $statements,
    $methodPct,
    $methodsHit,
    $methods
);

$ok = true;
if ($linePct + 1e-9 < $minLines) {
    fwrite(STDERR, sprintf("FAIL: line coverage %.2f%% < %.2f%%\n", $linePct, $minLines));
    $ok = false;
}
if ($methodPct + 1e-9 < $minMethods) {
    fwrite(STDERR, sprintf("FAIL: method coverage %.2f%% < %.2f%%\n", $methodPct, $minMethods));
    $ok = false;
}

exit($ok ? 0 : 1);
