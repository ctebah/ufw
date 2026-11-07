<?php
namespace Ufw\Form\Filter;

/** Filter with constructor options for array-definition tests. */
class ConstructibleForTests extends Base
{
    /** @var string */
    private $replacement;

    public function __construct($replacement = ' ')
    {
        $this->replacement = (string) $replacement;
    }

    public function filter($value)
    {
        return is_string($value) ? preg_replace("~[\r\n]~", $this->replacement, $value) : $value;
    }
}
