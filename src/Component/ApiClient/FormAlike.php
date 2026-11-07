<?php
namespace Ufw\Component\ApiClient;

/**
 * Lightweight {@see \Ufw\Form\Base} for API payloads: {@see populate()} / {@see getValues()} only; {@see isValid()} is always true.
 */
class FormAlike extends \Ufw\Form\Base
{

    /**
     * @var array<string, mixed>|null
     */
    protected $values;

    /**
     * @return $this
     */
    protected function init()
    {
        return $this;
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        return true;
    }

    /**
     * @param array<string, mixed> $values
     * @return $this
     */
    public function populate($values)
    {
        $this->values = $values;
        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getValues()
    {
        return $this->values;
    }
}
