<?php

$local = array(
    'ident' => 'local',
    'host' => '127.0.0.1',
    'user' => 'root',
    'pass' => 'ufw',
    'dbname' => 'ufw_test',
    'port' => '3306',
);

return array(
    '___opts' => array(
        'logFile' => __DIR__ . '/../log/custom_dblog.tmp',
        'logPost' => false,
    ),
    'local' => $local,
    'default' => $local,
    'master_server' => $local,
);
