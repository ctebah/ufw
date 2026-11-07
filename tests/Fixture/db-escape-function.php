<?php
namespace Ufw\Db;

function mysqli_real_escape_string($link, $string)
{
    if (\Ufw\Tests\Fixture\DbEscapeSwitch::$empty) {
        \Ufw\Tests\Fixture\DbEscapeSwitch::$calls++;
        return '';
    }
    return \mysqli_real_escape_string($link, $string);
}
