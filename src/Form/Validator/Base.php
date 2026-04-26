<?php
namespace Ufw\Form\Validator;

/**
 * Abstract field validator: message templates/variables, optional element link, and {@see isValid()} against submitted context.
 */
abstract class Base
{

    /**
     * Array of validation failure messages
     *
     * @var array
     */
    protected $messages = array();

    /**
     * Validation failure message template definitions
     *
     * @var array
     */
    protected $messageTemplates = array();

    /**
     * Additional variables available for validation failure messages
     *
     * @var array
     */
    protected $messageVariables = array();

    /**
     * Flag indidcating whether or not value should be obfuscated in error
     * messages
     *
     * @var bool
     */
    protected $obscureValue = false;

    /**
     *
     * @var \Ufw\Form\Element\Base
     */
    protected $element = null;

    protected $value;

    abstract public function isValid($value, $context = null);

    /**
     * Key for {@see \Ufw\Form\Validators::addValidator()} when passing an instance.
     * Derived from {@see get_class()} (backslashes → underscores) for backward compatibility and stable keys for anonymous classes.
     */
    public function getName(): string
    {
        return trim(preg_replace('~\\\\~', '_', get_class($this)), '\\');
    }

    public function getErrors()
    {
        return $this->getMessages();
    }

    /**
     * Sets the value to be validated and clears the messages and errors arrays
     *
     * @param mixed $value
     * @return void
     */
    protected function setValue($value)
    {
        $this->value = $value;
        $this->messages = array();
    }

    /**
     *
     * @param string $messageKey
     * @param string $value
     *            OPTIONAL
     * @return void
     */
    protected function error($messageKey, $value = null)
    {
        if ($messageKey === null) {
            $keys = array_keys($this->messageTemplates);
            $messageKey = current($keys);
        }
        if ($value === null) {
            $value = $this->value;
        }
        $this->messages[$messageKey] = $this->createMessage($messageKey, $value);
    }

    /**
     * Constructs and returns a validation failure message with the given message key and value.
     *
     * Returns null if and only if $messageKey does not correspond to an existing template.
     *
     * If a translator is available and a translation exists for $messageKey,
     * the translation will be used.
     *
     * @param string $messageKey
     * @param string $value
     * @return string
     */
    protected function createMessage($messageKey, $value)
    {
        if (!isset($this->messageTemplates[$messageKey])) {
            return null;
        }

        $message = $this->messageTemplates[$messageKey];

        if (is_object($value)) {
            if (!in_array('__toString', get_class_methods($value))) {
                $value = get_class($value) . ' object';
            } else {
                $value = $value->__toString();
            }
        } elseif (is_array($value)) {
            $value = $this->implodeRecursive($value);
        } else {
            $value = implode((array) $value);
        }

        if ($this->getObscureValue()) {
            $value = str_repeat('*', strlen($value));
        }

        $message = str_replace('%value%', $value, $message);
        foreach ($this->messageVariables as $ident => $property) {
            $message = str_replace("%$ident%", implode(' ', (array) $this->$property), $message);
        }

        return $this->translate($message);
    }

    /**
     * Joins elements of a multidimensional array
     *
     * @param array $pieces
     * @return string
     */
    protected function implodeRecursive(array $pieces)
    {
        $values = array();
        foreach ($pieces as $item) {
            if (is_array($item)) {
                $values[] = $this->implodeRecursive($item);
            } else {
                $values[] = $item;
            }
        }

        return implode(', ', $values);
    }

    /**
     * Retrieve flag indicating whether or not value should be obfuscated in
     * messages
     *
     * @return bool
     */
    public function getObscureValue()
    {
        return $this->obscureValue;
    }

    /**
     * Set flag indicating whether or not value should be obfuscated in messages
     *
     * @param bool $flag
     */
    public function setObscureValue($flag)
    {
        $this->obscureValue = (bool) $flag;
        return $this;
    }

    /**
     * Returns an array of the names of variables that are used in constructing validation failure messages
     *
     * @return array
     */
    public function getMessageVariables()
    {
        return array_keys($this->messageVariables);
    }

    /**
     * Returns the message templates from the validator
     *
     * @return array
     */
    public function getMessageTemplates()
    {
        return $this->messageTemplates;
    }

    /**
     * Returns array of validation failure messages
     *
     * @return array
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     *
     * @return \Ufw\Form\Element\Base
     */
    public function getElement()
    {
        return $this->element;
    }

    /**
     *
     * @param \Ufw\Form\Element\Base $element
     */
    public function setElement(\Ufw\Form\Element\Base $element = null)
    {
        $this->element = $element;
        return $this;
    }

    /**
     *
     * @return \Ufw\Application
     */
    protected function getApplication()
    {
        return \Ufw\Registry::getInstance()->get('application');
    }

    protected function getHelper($name)
    {
        return $this->getApplication()->getHelper($name);
    }

    protected function translate($str)
    {
        return $this->getHelper('translate')->translate($str);
    }
}
