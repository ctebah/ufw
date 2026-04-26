<?php

namespace Ufw\Helper;

/**
 * Applies the optional `translator` callable from {@see Registry} to a message string.
 * If no translator is registered, the input is returned unchanged.
 * Invokable so the helper can be used as `$helper('…')` after resolution by name.
 */
class Translate extends Base
{

    public function __invoke($str)
    {
        return $this->translate($str);
    }

    public function translate($str)
    {
        if ($translator = \Ufw\Registry::getInstance()->get('translator')) {
            $str = $translator($str);
        }
        return $str;
    }
}
