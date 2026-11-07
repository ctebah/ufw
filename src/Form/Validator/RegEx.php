<?php
namespace Ufw\Form\Validator;

/** PCRE pattern match / non-match validation. */
class RegEx extends Base
{

    const INVALID = 'regexInvalid';

    const NOT_MATCH = 'regexNotMatch';

    const ERROROUS = 'regexErrorous';

    /**
     * @var array<string, string>
     */
    protected $messageTemplates = array(
        self::INVALID => "Invalid type given. String, integer or float expected",
        self::NOT_MATCH => "'%value%' does not match against validation pattern",
        self::ERROROUS => "There was an internal error while using the pattern '%pattern%'"
    );

    /**
     * @var string
     */
    protected $_pattern;

    /**
     * @param string $pattern
     */
    public function __construct($pattern)
    {
        $this->setPattern($pattern);
    }

    /**
     * @return string
     */
    public function getPattern()
    {
        return $this->_pattern;
    }

    /**
     * @param string $pattern
     * @return $this
     */
    public function setPattern($pattern)
    {
        $this->_pattern = (string) $pattern;
        return $this;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     * @return bool
     */
    public function isValid($value, $context = null)
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            $this->error(self::INVALID);
            return false;
        }

        $stringValue = (string) $value;
        if (!strlen($stringValue)) {
            return true;
        }

        $this->setValue($stringValue);

        $status = $this->matchPattern($stringValue);

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

    /**
     * @param string $stringValue
     * @return int|false
     */
    private function matchPattern($stringValue)
    {
        set_error_handler(static function () {
            return true;
        }, E_WARNING);
        try {
            return preg_match($this->_pattern, $stringValue);
        } finally {
            restore_error_handler();
        }
    }
}
