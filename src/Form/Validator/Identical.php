<?php
namespace Ufw\Form\Validator;

/** Compares value to a fixed token or another field in context (e.g. password repeat). */
class Identical extends Base
{

    /**
     * Error codes
     * @const string
     */
    const NOT_SAME = 'notSame';

    const MISSING_TOKEN = 'missingToken';

    /**
     * Error messages
     *
     * @var array
     */
    protected $messageTemplates = array(
        self::NOT_SAME => "The two given tokens do not match",
        self::MISSING_TOKEN => 'No token was provided to match against'
    );

    /**
     *
     * @var array
     */
    protected $messageVariables = array(
        'token' => '_tokenString'
    );

    protected $token;

    /**
     * Sets validator options
     *
     * @param mixed $token
     */
    public function __construct($token = null)
    {
        $this->setToken($token);
    }

    /**
     * Retrieve token
     *
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Set token against which to compare
     *
     * @param mixed $token
     * @return Identical
     */
    public function setToken($token)
    {
        $this->token = $token;
        return $this;
    }

    /**
     * Returns true if and only if a token has been set and the provided value
     * matches that token.
     *
     * @param mixed $value
     * @param array $context
     * @return boolean
     */
    public function isValid($value, $context = null)
    {
        $this->setValue($value);
        
        if (($context !== null) && isset($context) && array_key_exists($this->getToken(), $context)) {
            $token = $context[$this->getToken()];
        } else {
            $token = $this->getToken();
        }
        
        if ($token === null) {
            $this->error(self::MISSING_TOKEN);
            return false;
        }
        
        if ($value != $token) {
            $this->error(self::NOT_SAME);
            return false;
        }
        
        return true;
    }
}
