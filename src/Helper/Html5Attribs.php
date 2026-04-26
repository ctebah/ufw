<?php

namespace Ufw\Helper;

/**
 * Turns an associative attribute map into an HTML5-safe attribute string (booleans as minimized
 * attributes, scalars quoted with {@see htmlspecialchars}). Invokable as `$helper($attribs)`.
 */
class Html5Attribs extends Base
{

    public function __invoke($attribs)
    {
        return $this->html5Attribs($attribs);
    }

    public function html5Attribs($attribs)
    {
        $out = [];
        foreach ($attribs as $key => $value) {
            if (is_null($value)) {
                continue;
            } elseif (is_bool($value)) {
                if ($value) $out[] = "$key";
            } else {
                $out[] = "$key=\"" . htmlspecialchars($value, ENT_COMPAT | ENT_HTML5) . "\"";
            }
        }
        return join(' ', $out);
    }
}
