<?php

$pass = getenv('UFW_TEST_DB_PASS');
if ($pass === false) {
    $pass = 'ufw';
}

$local = array(
    'ident' => 'local',
    'host' => getenv('UFW_TEST_DB_HOST') ?: '127.0.0.1',
    'user' => getenv('UFW_TEST_DB_USER') ?: 'root',
    'pass' => $pass,
    'dbname' => getenv('UFW_TEST_DB_NAME') ?: 'ufw_test',
    'port' => getenv('UFW_TEST_DB_PORT') ?: '13306',
    'collation' => 'utf8mb4',
);

$connections = array(
    'local' => $local,
);
$connections['default'] = & $connections['local'];
$connections['master_server'] = & $connections['local'];
$connections['___opts'] = array(
    'throwOnError' => true,
    'logPost' => true,
    'note' => 'ignored',
);

return $connections;
