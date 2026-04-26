<?php
namespace Ufw\Form\Filter;

/** Collapses line breaks to spaces for single-line fields. */
class Singleline extends Base
{
    public function filter($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                $value[$key] = $this->filter($val);
            }
            return $value;
        } else {
            return preg_replace("~[\r\n]~", ' ', $value);
        }
    }
}
