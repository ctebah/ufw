<?php
namespace Ufw\Form\Filter;

/** Trims leading/trailing whitespace. */
class Trim extends Base
{

    /**
     * @var string
     */
    protected $characterMask = ' ';

    /**
     * @param string $character_mask
     */
    public function __construct($character_mask = " \t\n\r\0\x0B")
    {
        $this->setCharacterMask($character_mask);
    }

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
            return trim($value, $this->getCharacterMask());
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return trim((string) $value, $this->getCharacterMask());
        }
        return $value;
    }

    /**
     * @return string
     */
    public function getCharacterMask()
    {
        return $this->characterMask;
    }

    /**
     * @param string $characterMask
     * @return $this
     */
    public function setCharacterMask($characterMask)
    {
        $this->characterMask = $characterMask;
        return $this;
    }
}
