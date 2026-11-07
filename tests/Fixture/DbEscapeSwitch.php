<?php
namespace Ufw\Tests\Fixture;

/**
 * Test switch for the namespaced stand-in of mysqli_real_escape_string.
 * quote() treats an empty return as a stale connection. PHP 8 throws on a closed mysqli instead, so the branch cannot be reached that way.
 */
class DbEscapeSwitch
{
    public static $empty = false;

    public static $calls = 0;
}
