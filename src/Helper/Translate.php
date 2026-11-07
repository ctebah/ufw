<?php

namespace Ufw\Helper;

/**
 * Applies the optional `translator` callable from {@see Registry} to a message string.
 * If no translator is registered, the input is returned unchanged.
 * Invokable so the helper can be used as `$helper('…')` after resolution by name.
 */
class Translate extends Base
{

    /**
     * @param string $str
     * @return string
     */
    public function __invoke($str)
    {
        return $this->translate($str);
    }

    /**
     * @param string $str
     * @return string
     */
    public function translate($str)
    {
        $translator = \Ufw\Registry::getInstance()->get('translator');
        if (is_callable($translator)) {
            $translated = $translator($str);
            if (is_string($translated)) {
                $str = $translated;
            }
        }
        return $str;
    }
}
