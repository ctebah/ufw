<?php
namespace Ufw\Form\Validator;

/**
 * Abstract field validator: message templates/variables, optional element link, and {@see isValid()} against submitted context.
 */
abstract class Base
{

    /**
     * @var array<string, string>
     */
    protected $messages = array();

    /**
     * @var array<string, string>
     */
    protected $messageTemplates = array();

    /**
     * @var array<string, string>
     */
    protected $messageVariables = array();

    /**
     * @var bool
     */
    protected $obscureValue = false;

    /**
     * @var \Ufw\Form\Element\Base|null
     */
    protected $element = null;

    /**
     * @var mixed
     */
    protected $value;

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     * @return bool
     */
    abstract public function isValid($value, $context = null);

    /**
     * Key for {@see \Ufw\Form\Validators::addValidator()} when passing an instance.
     */
    public function getName(): string
    {
        return trim((string) preg_replace('~\\\\~', '_', get_class($this)), '\\');
    }

    /**
     * @return array<string, string>
     */
    public function getErrors()
    {
        return $this->getMessages();
    }

    /**
     * @param mixed $value
     * @return void
     */
    protected function setValue($value)
    {
        $this->value = $value;
        $this->messages = array();
    }

    /**
     * @param string|null $messageKey
     * @param mixed $value
     * @return void
     */
    protected function error($messageKey, $value = null)
    {
        if ($messageKey === null) {
            $keys = array_keys($this->messageTemplates);
            $messageKey = (string) current($keys);
        }
        if ($messageKey === '') {
            return;
        }
        if ($value === null) {
            $value = $this->value;
        }
        $message = $this->createMessage($messageKey, $value);
        if ($message !== null) {
            $this->messages[$messageKey] = $message;
        }
    }

    /**
     * @param string $messageKey
     * @param mixed $value
     * @return string|null
     */
    protected function createMessage($messageKey, $value)
    {
        if (!isset($this->messageTemplates[$messageKey])) {
            return null;
        }

        $message = $this->messageTemplates[$messageKey];

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                $value = (string) $value;
            } else {
                $value = get_class($value) . ' object';
            }
        } elseif (is_array($value)) {
            $value = $this->implodeRecursive($value);
        } elseif (is_scalar($value)) {
            $value = (string) $value;
        } else {
            $value = '';
        }

        if ($this->getObscureValue()) {
            $value = str_repeat('*', strlen((string) $value));
        }

        $message = str_replace('%value%', (string) $value, $message);
        foreach ($this->messageVariables as $ident => $property) {
            $message = str_replace("%$ident%", $this->messageVariableToString($this->$property), $message);
        }

        return $this->translate($message);
    }

    /**
     * @param array<int|string, mixed> $pieces
     * @return string
     */
    protected function implodeRecursive(array $pieces)
    {
        $values = array();
        foreach ($pieces as $item) {
            if (is_array($item)) {
                $values[] = $this->implodeRecursive($item);
            } else {
                $values[] = $this->scalarOrObjectToString($item);
            }
        }

        return implode(', ', $values);
    }

    /**
     * @param mixed $value
     * @return string
     */
    protected function messageVariableToString($value)
    {
        if (is_array($value)) {
            $parts = array();
            foreach ($value as $item) {
                $parts[] = $this->scalarOrObjectToString($item);
            }
            return implode(' ', $parts);
        }

        return $this->scalarOrObjectToString($value);
    }

    /**
     * @param mixed $value
     * @return string
     */
    protected function scalarOrObjectToString($value)
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return '';
    }

    /**
     * @return bool
     */
    public function getObscureValue()
    {
        return $this->obscureValue;
    }

    /**
     * @param bool $flag
     * @return $this
     */
    public function setObscureValue($flag)
    {
        $this->obscureValue = (bool) $flag;
        return $this;
    }

    /**
     * @return list<string>
     */
    public function getMessageVariables()
    {
        return array_keys($this->messageVariables);
    }

    /**
     * @return array<string, string>
     */
    public function getMessageTemplates()
    {
        return $this->messageTemplates;
    }

    /**
     * @return array<string, string>
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * @return \Ufw\Form\Element\Base|null
     */
    public function getElement()
    {
        return $this->element;
    }

    /**
     * @param \Ufw\Form\Element\Base|null $element
     * @return $this
     */
    public function setElement(\Ufw\Form\Element\Base $element = null)
    {
        $this->element = $element;
        return $this;
    }

    /**
     * @return \Ufw\Application
     */
    protected function getApplication()
    {
        $application = \Ufw\Registry::getInstance()->get('application');
        if (!$application instanceof \Ufw\Application) {
            throw new \RuntimeException('Application is not registered.');
        }
        return $application;
    }

    /**
     * @param string $name
     * @return \Ufw\Helper\Base|null
     */
    protected function getHelper($name)
    {
        return $this->getApplication()->getHelper($name);
    }

    /**
     * @param string $str
     * @return string
     */
    protected function translate($str)
    {
        $helper = $this->getHelper('translate');
        if ($helper === null) {
            return $str;
        }

        return $helper->translate($str);
    }
}
