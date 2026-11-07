<?php
/**
 * Write unified gap report: coverage/gaps.txt (uncovered methods and lines together).
 *
 * Usage: php bin/coverage-gaps.php [clover.xml]
 */
require __DIR__ . '/coverage-gaps-lib.php';

$root = dirname(__DIR__);
$clover = $argv[1] ?? $root . '/coverage/clover.xml';
$report = $root . '/coverage/gaps.txt';

if (!is_file($clover)) {
    fwrite(STDERR, "Missing Clover report: $clover\nRun: composer test-all\n");
    exit(1);
}

$x = simplexml_load_file($clover);
$methodGaps = coverage_collect_method_gaps($x);
$lineGaps = coverage_collect_line_gaps($x);
coverage_write_gaps_file($report, $methodGaps, $lineGaps);

$methodTotal = 0;
foreach ($methodGaps as $methods) {
    $methodTotal += count($methods);
}
$lineTotal = 0;
foreach ($lineGaps as $lines) {
    $lineTotal += count($lines);
}

echo "Written: $report\n";
echo "Uncovered methods: $methodTotal\n";
echo "Uncovered lines:   $lineTotal\n";
