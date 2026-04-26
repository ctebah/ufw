<?php

namespace Ufw;

/**
 * Debug exit: prints a message, then the caller file/line from {@see debug_backtrace()}, and terminates.
 */
function mydie($msg = '')
{
    echo $msg;
    $bt = debug_backtrace();
    $caller = array_shift($bt);
    echo "<br>" . $caller['line'] . ' @ ' . $caller['file'];
    die();
}