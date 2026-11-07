<?php
namespace Ufw\Form\Validator;

/**
 * Email shape validation via built-in HTML5-style pattern on top of {@see RegEx}.
 */
class Email extends RegEx
{

    /**
     * @var array<string, string>
     */
    protected $messageTemplates = array(
        self::INVALID => "Invalid type given. String, integer or float expected",
        self::NOT_MATCH => "'%value%' is not valid email address",
        self::ERROROUS => "There was an internal error while using the pattern '%pattern%'"
    );

    public function __construct()
    {
        parent::__construct('~^[a-zA-Z0-9.!#$%&’*+\/=?^_\'{|}\~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,253}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,253}[a-zA-Z0-9])?)*$~');
    }
}
