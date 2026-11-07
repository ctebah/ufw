<?php
namespace Ufw\Form\Validator;

/** Fails empty/null/empty-string values (with optional “allow empty array” behaviour). */
class Required extends Base
{

    const REQUIRED = 'required-value';

    /**
     * @var array<string, string>
     */
    protected $messageTemplates = array(
        self::REQUIRED => 'Value is required and can\'t be empty'
    );

    /**
     * @var bool
     */
    protected $allowMultiple = false;

    /**
     * @param bool $allowMultiple
     */
    public function __construct($allowMultiple = false)
    {
        $this->setAllowMultiple($allowMultiple);
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     * @return bool
     */
    public function isValid($value, $context = null)
    {
        if ($this->getAllowMultiple()) {
            $result = is_array($value) ? count($value) : 0;
        } elseif (is_scalar($value)) {
            $result = strlen((string) $value);
        } else {
            $result = 0;
        }
        if (!$result) {
            $this->error(self::REQUIRED);
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function getAllowMultiple()
    {
        return $this->allowMultiple;
    }

    /**
     * @param bool $allowMultiple
     * @return $this
     */
    public function setAllowMultiple($allowMultiple)
    {
        $this->allowMultiple = (bool) $allowMultiple;
        return $this;
    }
}
