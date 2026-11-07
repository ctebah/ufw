<?php

namespace Ufw;

/**
 * @param mixed $value
 * @return array<string, mixed>
 */
function filter_string_keyed_array($value)
{
    if (!is_array($value)) {
        return array();
    }
    $out = array();
    foreach ($value as $key => $val) {
        if (is_string($key)) {
            $out[$key] = $val;
        }
    }

    return $out;
}
