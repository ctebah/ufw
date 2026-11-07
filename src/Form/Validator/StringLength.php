<?php
namespace Ufw\Form\Validator;

/** String length bounds (`min` / `max` options). */
class StringLength extends Base
{

    const INVALID = 'stringLengthInvalid';

    const TOO_SHORT = 'stringLengthTooShort';

    const TOO_LONG = 'stringLengthTooLong';

    /**
     * @var array<string, string>
     */
    protected $messageTemplates = array(
        self::INVALID => "Invalid type given. String expected",
        self::TOO_SHORT => "'%value%' is less than %min% characters long",
        self::TOO_LONG => "'%value%' is more than %max% characters long"
    );

    /**
     * @var int
     */
    protected $maxLen = 255;

    /**
     * @var int|null
     */
    protected $minLen = null;

    /**
     * @var array<string, string>
     */
    protected $messageVariables = array(
        'max' => 'maxLen',
        'min' => 'minLen'
    );

    /**
     * @param int $maxLen
     * @param int|null $minLen
     */
    public function __construct($maxLen = 255, $minLen = null)
    {
        $this->setMaxLen($maxLen)->setMinLen($minLen);
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     * @return bool
     */
    public function isValid($value, $context = null)
    {
        $this->setValue($value);

        if (is_scalar($value)) {
            $encoded = mb_convert_encoding((string) $value, 'ISO-8859-1', 'UTF-8');
            $length = is_string($encoded) ? strlen($encoded) : strlen((string) $value);
        } else {
            $length = 0;
        }

        if ($this->getMaxLen() < $length) {
            $this->error(self::TOO_LONG);
        }

        if ($this->getMinLen() !== null && ($this->getMinLen() > $length)) {
            $this->error(self::TOO_SHORT);
        }

        if (count($this->messages)) {
            return false;
        }

        return true;
    }

    /**
     * @return int
     */
    public function getMaxLen()
    {
        return $this->maxLen;
    }

    /**
     * @param int $maxLen
     * @return $this
     */
    public function setMaxLen($maxLen)
    {
        $this->maxLen = (int) $maxLen;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getMinLen()
    {
        return $this->minLen;
    }

    /**
     * @param int|null $minLen
     * @return $this
     */
    public function setMinLen($minLen)
    {
        $this->minLen = $minLen === null ? null : (int) $minLen;
        return $this;
    }
}
