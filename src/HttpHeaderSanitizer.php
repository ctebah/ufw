<?php

declare(strict_types=1);

namespace Ufw;

/**
 * Trims HTTP header names and values (including per-value entries in header arrays) before they are passed into
 * Laminas Diactoros / PSR-7. Mitigates CVE-2023-29530 / LP-2023-01 (multiline header termination); apply
 * to untrusted input such as SAPI `getallheaders()`-style arrays at the edge of request marshalling.
 *
 * @see https://getlaminas.org/security/advisory/LP-2023-01
 */
class HttpHeaderSanitizer
{
    public static function sanitizeName(string $name): string
    {
        return trim($name);
    }

    /**
     * @param mixed $value
     * @return string|array<int|string, string>
     */
    public static function sanitizeValue($value)
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $v) {
                if (is_string($v)) {
                    $out[] = trim($v);
                }
            }
            return $out;
        }

        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }

    /**
     * SAPI/marshalHeadersFromSapi: normalize leading/trailing whitespace on keys and values.
     *
     * @param array<int|string, mixed> $headers
     * @return array<string, string|array<int|string, string>>
     */
    public static function sanitizeHeadersArray(array $headers): array
    {
        $out = [];
        foreach ($headers as $name => $value) {
            $headerName = is_string($name) ? $name : (string) $name;
            $out[self::sanitizeName($headerName)] = self::sanitizeValue($value);
        }

        return $out;
    }
}
