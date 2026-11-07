<?php
namespace Ufw\Form\Validator;

/** Compares value to a fixed token or another field in context (e.g. password repeat). */
class Identical extends Base
{

    const NOT_SAME = 'notSame';

    const MISSING_TOKEN = 'missingToken';

    /**
     * @var array<string, string>
     */
    protected $messageTemplates = array(
        self::NOT_SAME => "The two given tokens do not match",
        self::MISSING_TOKEN => 'No token was provided to match against'
    );

    /**
     * @var array<string, string>
     */
    protected $messageVariables = array(
        'token' => 'token'
    );

    /**
     * @var mixed
     */
    protected $token;

    /**
     * @param mixed $token
     */
    public function __construct($token = null)
    {
        $this->setToken($token);
    }

    /**
     * @return mixed
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param mixed $token
     * @return $this
     */
    public function setToken($token)
    {
        $this->token = $token;
        return $this;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     * @return bool
     */
    public function isValid($value, $context = null)
    {
        $this->setValue($value);

        $tokenKey = $this->getToken();
        if (is_array($context) && (is_string($tokenKey) || is_int($tokenKey)) && array_key_exists($tokenKey, $context)) {
            $token = $context[$tokenKey];
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
