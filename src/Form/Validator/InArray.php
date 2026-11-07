<?php
namespace Ufw\Form\Validator;

/** Value must appear in the element option list ({@see InArray::$haystack}); uses loose comparison like PHP `in_array` without strict. */
class InArray extends Base
{

    const NOT_IN_ARRAY = 'notInArray';

    /**
     * @var array<string, string>
     */
    protected $messageTemplates = array(
        self::NOT_IN_ARRAY => "'%value%' was not found in the list of available options"
    );

    /**
     * @var array<int|string, mixed>
     */
    protected $haystack;

    /**
     * @param array<int|string, mixed> $haystack
     */
    public function __construct($haystack)
    {
        $this->setHaystack($haystack);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getHaystack()
    {
        return $this->haystack;
    }

    /**
     * @param array<int|string, mixed> $haystack
     * @return $this
     */
    public function setHaystack(array $haystack)
    {
        $this->haystack = $haystack;
        return $this;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     * @return bool
     */
    public function isValid($value, $context = null)
    {
        $element = $this->getElement();
        if ($element instanceof \Ufw\Form\Element\Multi) {
            $this->setHaystack($element->getMultiOptionValues());
        }

        if ($value === null) {
            return true;
        }

        $this->setValue($value);

        if (is_scalar($value)) {
            if (in_array($value, $this->haystack)) {
                return true;
            }
        } elseif (is_array($value)) {
            foreach ($value as $item) {
                if (!in_array($item, $this->haystack)) {
                    $this->error(self::NOT_IN_ARRAY);
                    return false;
                }
            }
            return true;
        }

        $this->error(self::NOT_IN_ARRAY);
        return false;
    }
}
