<?php
namespace Ufw\Form\Validator;

/** String length bounds (`min` / `max` options). */
class StringLength extends Base
{

    const INVALID = 'stringLengthInvalid';

    const TOO_SHORT = 'stringLengthTooShort';

    const TOO_LONG = 'stringLengthTooLong';

    /**
     *
     * @var array
     */
    protected $messageTemplates = array(
        self::INVALID => "Invalid type given. String expected",
        self::TOO_SHORT => "'%value%' is less than %min% characters long",
        self::TOO_LONG => "'%value%' is more than %max% characters long"
    );

    protected $maxLen = 255;

    protected $minLen = null;

    protected $messageVariables = array(
        'max' => 'maxLen',
        'min' => 'minLen'
    );

    public function __construct($maxLen = 255, $minLen = null)
    {
        $this->setMaxLen($maxLen)->setMinLen($minLen);
    }

    public function isValid($value, $context = null)
    {
        $this->setValue($value);
        
        $length = isset($value) ? strlen(mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8')) : 0;
        
        if ((null !== $this->getMaxLen()) && ($this->getMaxLen() < $length)) {
            $this->error(self::TOO_LONG);
        }
        
        if ((null !== $this->getMinLen()) && ($this->getMinLen() > $length)) {
            $this->error(self::TOO_SHORT);
        }
        
        if (count($this->messages)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     *
     * @return int
     */
    public function getMaxLen()
    {
        return $this->maxLen;
    }

    /**
     *
     * @param int $maxLen
     */
    public function setMaxLen($maxLen)
    {
        $this->maxLen = $maxLen;
        return $this;
    }

    /**
     *
     * @return int
     */
    public function getMinLen()
    {
        return $this->minLen;
    }

    /**
     *
     * @param int $maxLen
     */
    public function setMinLen($minLen)
    {
        $this->minLen = $minLen;
        return $this;
    }
}
