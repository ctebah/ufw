<?php
return array(
    'assets' => array(
        'serve-minified' => false,
    ),
    'dispatch' => array(
        'routes' => array(),
        'controllers' => array('ping'),
        'defaults' => array(
            'controller' => 'ping',
            'action' => 'index',
        ),
    ),
);
