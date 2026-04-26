<?php
namespace Ufw\Form\Filter;

/** Trims leading/trailing whitespace. */
class Trim extends Base
{

    protected $characterMask = ' ';

    public function __construct($character_mask = " \t\n\r\0\x0B")
    {
        $this->setCharacterMask($character_mask);
    }

    public function filter($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                $value[$key] = $this->filter($val);
            }
            return $value;
        } else {
            return trim($value, $this->getCharacterMask());
        }
    }

    /**
     *
     * @return string
     */
    public function getCharacterMask()
    {
        return $this->characterMask;
    }

    /**
     *
     * @param string $characterMask
     */
    public function setCharacterMask($characterMask)
    {
        $this->characterMask = $characterMask;
        return $this;
    }
}
