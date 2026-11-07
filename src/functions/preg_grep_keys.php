<?php

namespace Ufw;

/**
 * @param string $pattern
 * @param array<int|string, mixed> $input
 * @param int $flags
 * @return array<int|string, mixed>
 */
function preg_grep_keys($pattern, $input, $flags = 0)
{
    $keys = @preg_grep($pattern, array_keys($input), $flags);
    if ($keys === false) {
        return array();
    }

    return array_intersect_key($input, array_flip(array_values(array_filter($keys, function ($key) {
        return is_int($key) || is_string($key);
    }))));
}
