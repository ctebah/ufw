<?php

namespace Ufw\Helper;

/**
 * Turns an associative attribute map into an HTML5-safe attribute string (booleans as minimized
 * attributes, scalars quoted with {@see htmlspecialchars}). Invokable as `$helper($attribs)`.
 */
class Html5Attribs extends Base
{

    /**
     * @param array<string, mixed> $attribs
     * @return string
     */
    public function __invoke($attribs)
    {
        return $this->html5Attribs($attribs);
    }

    /**
     * @param array<string, mixed> $attribs
     * @return string
     */
    public function html5Attribs($attribs)
    {
        $out = [];
        foreach ($attribs as $key => $value) {
            if (is_null($value)) {
                continue;
            } elseif (is_bool($value)) {
                if ($value) $out[] = "$key";
            } elseif (is_string($value) || is_int($value) || is_float($value)) {
                $out[] = "$key=\"" . htmlspecialchars((string) $value, ENT_COMPAT | ENT_HTML5) . "\"";
            }
        }
        return join(' ', $out);
    }
}
