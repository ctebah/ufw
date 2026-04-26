<?php
namespace Ufw\Component\ApiClient;

/**
 * Lightweight {@see \Ufw\Form\Base} for API payloads: {@see populate()} / {@see getValues()} only; {@see isValid()} is always true.
 */
class FormAlike extends \Ufw\Form\Base
{

    protected $values;

    protected function init()
    {}

    public function isValid()
    {
        return true;
    }

    public function populate($values)
    {
        $this->values = $values;
    }

    public function getValues()
    {
        return $this->values;
    }
}