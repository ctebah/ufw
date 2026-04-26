<?php
namespace Ufw\Form\Validator;

/** Fails empty/null/empty-string values (with optional “allow empty array” behaviour). */
class Required extends Base
{

    const REQUIRED = 'required-value';

    /**
     *
     * @var array
     */
    protected $messageTemplates = array(
        self::REQUIRED => 'Value is required and can\'t be empty'
    );

    /**
     *
     * @var boolean
     */
    protected $allowMultiple;

    /**
     * Sets validator options
     *
     * @param array $options
     *            Validator options
     */
    public function __construct($allowMultiple = false)
    {
        $this->setAllowMultiple($allowMultiple);
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
        $result = $this->getAllowMultiple() ? count($value) : strlen($value);
        if (!$result) {
            $this->error(self::REQUIRED);
        }
        
        return $result;
    }

    /**
     *
     * @return boolean
     */
    public function getAllowMultiple()
    {
        return $this->allowMultiple;
    }

    /**
     *
     * @param boolean $allowMultiple
     */
    public function setAllowMultiple($allowMultiple)
    {
        $this->allowMultiple = $allowMultiple;
        return $this;
    }
}