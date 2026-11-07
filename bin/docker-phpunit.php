<?php

/**
 * Run PHPUnit inside the Docker test stack (MySQL + PHP 8.2 + PCOV).
 *
 * Usage: php bin/docker-phpunit.php [phpunit args…]
 * Default args: vendor/bin/phpunit
 */

$root = dirname(__DIR__);
$args = array_slice($argv, 1);
if ($args === array()) {
    $args = array('vendor/bin/phpunit');
}

if (DIRECTORY_SEPARATOR === '\\') {
    $script = $root . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'phpunit.ps1';
    $command = 'powershell -NoProfile -ExecutionPolicy Bypass -File '
        . escapeshellarg($script);
    foreach ($args as $arg) {
        $command .= ' ' . escapeshellarg($arg);
    }
} else {
    $script = $root . '/docker/phpunit.sh';
    $command = escapeshellarg($script);
    foreach ($args as $arg) {
        $command .= ' ' . escapeshellarg($arg);
    }
}

passthru($command, $exitCode);
exit((int) $exitCode);
