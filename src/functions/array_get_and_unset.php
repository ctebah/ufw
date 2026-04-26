<?php

namespace Ufw;

/**
 * Takes `$array[$key]` (or `$default`), removes that key from `$array`, and returns the value.
 *
 * @param array $array
 * @param string|int $key
 * @param mixed $default
 * @return mixed
 */
function array_get_and_unset(&$array, $key, $default = null)
{
    $value = isset($array[$key]) ? $array[$key] : $default;
    unset($array[$key]);
    return $value;
}