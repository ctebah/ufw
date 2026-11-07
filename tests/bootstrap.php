<?php

if (PHP_VERSION_ID < 80200) {
    fwrite(STDERR, "UFW tests run on PHP 8.2 or newer (library code stays compatible with PHP 7.4).\n");
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';

$app = \Ufw\Tests\Support\TestAppPaths::app();
if (!is_dir($app . '/log')) {
    mkdir($app . '/log', 0777, true);
}

$logs = array(
    $app . '/log/error_dblog.tmp',
    \Ufw\Tests\Support\TestAppPaths::throwApp() . '/log/error_dblog.tmp',
    \Ufw\Tests\Support\TestAppPaths::silentApp() . '/log/error_dblog.tmp',
);
foreach ($logs as $log) {
    $bak = $log . '.bak';
    if (is_file($bak)) {
        unlink($bak);
    }
    if (is_file($log)) {
        rename($log, $bak);
    }
}

\Ufw\Paths::setApplicationPath($app);
