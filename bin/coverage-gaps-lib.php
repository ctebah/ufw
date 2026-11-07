<?php
/**
 * Shared helpers for coverage gap reports.
 */

/**
 * @param list<int> $lines
 */
function coverage_format_line_ranges(array $lines): string
{
    if ($lines === array()) {
        return '';
    }
    sort($lines);
    $ranges = array();
    $start = $lines[0];
    $end = $lines[0];
    $count = count($lines);
    for ($i = 1; $i < $count; $i++) {
        $n = $lines[$i];
        if ($n === $end + 1) {
            $end = $n;
            continue;
        }
        $ranges[] = $start === $end ? (string) $start : $start . '-' . $end;
        $start = $end = $n;
    }
    $ranges[] = $start === $end ? (string) $start : $start . '-' . $end;

    return implode(', ', $ranges);
}

/**
 * @return string path relative to repo root
 */
function coverage_short_src_path(string $name): string
{
    $normalized = str_replace('\\', '/', $name);
    $short = preg_replace('~^.*/src/~', 'src/', $normalized);
    if ($short === null || $short === $normalized) {
        return $normalized;
    }

    return $short;
}

/**
 * @param SimpleXMLElement $x
 * @return array<string, list<string>>
 */
function coverage_collect_method_gaps($x): array
{
    $byClass = array();
    foreach ($x->xpath('//file') as $file) {
        $name = (string) $file['name'];
        $className = isset($file->class[0]) ? (string) $file->class[0]['name'] : null;
        foreach ($file->line as $line) {
            if ((string) $line['type'] !== 'method' || (int) $line['count'] > 0) {
                continue;
            }
            $method = (string) $line['name'];
            $cn = $className ?: basename(str_replace('\\', '/', $name), '.php');
            if (!isset($byClass[$cn])) {
                $byClass[$cn] = array();
            }
            $byClass[$cn][] = $method;
        }
    }
    ksort($byClass);
    foreach ($byClass as $cn => $methods) {
        $methods = array_values(array_unique($methods));
        sort($methods);
        $byClass[$cn] = $methods;
    }

    return $byClass;
}

/**
 * @param SimpleXMLElement $x
 * @return array<string, list<int>>
 */
function coverage_collect_line_gaps($x): array
{
    $byFile = array();
    foreach ($x->xpath('//file') as $file) {
        $name = (string) $file['name'];
        $short = coverage_short_src_path($name);
        foreach ($file->line as $line) {
            if ((string) $line['type'] !== 'stmt' || (int) $line['count'] > 0) {
                continue;
            }
            if (!isset($byFile[$short])) {
                $byFile[$short] = array();
            }
            $byFile[$short][] = (int) $line['num'];
        }
    }
    uasort($byFile, function ($a, $b) {
        return count($b) - count($a);
    });
    foreach ($byFile as $path => $lines) {
        sort($byFile[$path]);
    }

    return $byFile;
}

/**
 * @param array<string, list<string>> $methodGaps
 * @param array<string, list<int>> $lineGaps
 */
function coverage_write_gaps_file(string $path, array $methodGaps, array $lineGaps): void
{
    $methodTotal = 0;
    foreach ($methodGaps as $methods) {
        $methodTotal += count($methods);
    }
    $lineTotal = 0;
    foreach ($lineGaps as $lines) {
        $lineTotal += count($lines);
    }

    $out = array();
    $out[] = 'Coverage gaps (src/, including views/)';
    $out[] = 'Generated: ' . date('Y-m-d H:i:s');
    $out[] = '';
    $out[] = sprintf('Uncovered methods: %d', $methodTotal);
    $out[] = sprintf('Uncovered lines:   %d', $lineTotal);
    $out[] = '';
    $out[] = '=== Uncovered methods ===';
    if ($methodTotal === 0) {
        $out[] = '(none)';
    } else {
        foreach ($methodGaps as $class => $methods) {
            $out[] = $class . ': ' . implode(', ', $methods);
        }
    }
    $out[] = '';
    $out[] = '=== Uncovered lines (file => ranges) ===';
    if ($lineTotal === 0) {
        $out[] = '(none)';
    } else {
        foreach ($lineGaps as $file => $lines) {
            $out[] = sprintf(
                '%s [%d] %s',
                $file,
                count($lines),
                coverage_format_line_ranges($lines)
            );
        }
    }
    $out[] = '';

    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($path, implode("\n", $out));
}
