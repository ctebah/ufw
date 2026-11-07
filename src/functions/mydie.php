<?php

namespace Ufw;

/**
 * Debug exit: prints a message, then the caller file/line from {@see debug_backtrace()}, and terminates.
 *
 * @param mixed $msg
 * @return void
 */
function mydie($msg = '')
{
    if (is_string($msg)) {
        echo $msg;
    } elseif (is_int($msg) || is_float($msg)) {
        echo (string) $msg;
    } elseif (is_object($msg) && method_exists($msg, '__toString')) {
        echo (string) $msg;
    }
    $bt = debug_backtrace();
    $caller = array_shift($bt);
    $line = is_array($caller) && isset($caller['line']) ? $caller['line'] : '?';
    $file = is_array($caller) && isset($caller['file']) ? $caller['file'] : '?';
    echo "<br>" . $line . ' @ ' . $file;
    if (defined('UFW_MYDIE_SUPPRESS_EXIT') && UFW_MYDIE_SUPPRESS_EXIT) {
        return;
    }
    die();
}