<?php
namespace Ufw\Form\Filter;

/** Collapses line breaks to spaces for single-line fields. */
class Singleline extends Base
{
    /**
     * @param mixed $value
     * @return mixed
     */
    public function filter($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                $value[$key] = $this->filter($val);
            }
            return $value;
        }

        if (is_string($value)) {
            return preg_replace("~[\r\n]~", ' ', $value);
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return preg_replace("~[\r\n]~", ' ', (string) $value);
        }
        return $value;
    }
}
