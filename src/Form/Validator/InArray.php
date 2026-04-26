<?php
namespace Ufw\Form\Validator;

/** Value must appear in `haystack` (strict comparison optional). */
class InArray extends Base
{

    const NOT_IN_ARRAY = 'notInArray';

    /**
     *
     * @var array
     */
    protected $messageTemplates = array(
        self::NOT_IN_ARRAY => "'%value%' was not found in the list of available options"
    );

    /**
     * Haystack of possible values
     *
     * @var array
     */
    protected $haystack;

    /**
     * Sets validator options
     *
     * @param array $options
     *            Validator options
     */
    public function __construct($haystack)
    {
        $this->setHaystack($haystack);
    }

    /**
     * Returns the haystack option
     *
     * @return mixed
     */
    public function getHaystack()
    {
        return $this->haystack;
    }

    /**
     * Sets the haystack option
     *
     * @param mixed $haystack
     * @return inArray Provides a fluent interface
     */
    public function setHaystack(array $haystack)
    {
        $this->haystack = $haystack;
        return $this;
    }

    /**
     *
     * Returns true if and only if $value is contained in the haystack option.
     *
     * @param mixed $value
     * @return boolean
     */
    public function isValid($value, $context = null)
    {
        if (is_null($value)) {
            return true;
        }
        
        $this->setValue($value);
        
        if (is_scalar($value)) {
            if (in_array($value, $this->haystack)) {
                return true;
            }
        } else {
            if (!array_diff($value, $this->haystack)) {
                return true;
            }
        }
        
        $this->error(self::NOT_IN_ARRAY);
        return false;
    }
}
