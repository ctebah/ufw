<?php
namespace Ufw\Form\Validator;

/** PCRE pattern match / non-match validation. */
class RegEx extends Base
{

    const INVALID = 'regexInvalid';

    const NOT_MATCH = 'regexNotMatch';

    const ERROROUS = 'regexErrorous';

    /**
     *
     * @var array
     */
    protected $messageTemplates = array(
        self::INVALID => "Invalid type given. String, integer or float expected",
        self::NOT_MATCH => "'%value%' does not match against validation pattern",
        self::ERROROUS => "There was an internal error while using the pattern '%pattern%'"
    );

    /**
     * Regular expression pattern
     *
     * @var string
     */
    protected $_pattern;

    /**
     * Sets validator options
     */
    public function __construct($pattern)
    {
        $this->setPattern($pattern);
    }

    /**
     * Returns the pattern option
     *
     * @return string
     */
    public function getPattern()
    {
        return $this->_pattern;
    }

    /**
     * Sets the pattern option
     *
     * @param string $pattern
     */
    public function setPattern($pattern)
    {
        $this->_pattern = (string) $pattern;
        return $this;
    }

    /**
     * Returns true if and only if $value matches against the pattern option
     *
     * @param string $value
     * @return boolean
     */
    public function isValid($value, $context = null)
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            $this->error(self::INVALID);
            return false;
        }
        
        if (!strlen($value)) {
            return true;
        }
        
        $this->setValue($value);
        
        $status = @preg_match($this->_pattern, $value);
        
        if (false === $status) {
            $this->error(self::ERROROUS);
            return false;
        }
        
        if (!$status) {
            $this->error(self::NOT_MATCH);
            return false;
        }
        
        return true;
    }
}
