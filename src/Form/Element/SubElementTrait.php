<?php
namespace Ufw\Form\Element;

/**
 * Default wiring for {@see SubElementsInterface}: propagate {@see setForm}, {@see setValue}, and {@see isValid} to child elements.
 */
trait SubElementTrait {

    /**
     * @var list<\Ufw\Form\Element\Base>
     */
    protected $subEl = [];

    /**
     * @param \Ufw\Form\Base|null $form
     * @return $this
     */
    public function setForm(\Ufw\Form\Base $form = null)
    {
        parent::setForm($form);
        foreach ($this->subEl as $element) {
            $element->setForm($form);
        }
        return $this;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed>|null $context
     * @return $this
     */
    public function setValue($value, $context = null)
    {
        parent::setValue($value, $context);

        foreach ($this->subEl as $element) {
            $elementName = $element->getName();
            if (strpos($elementName, '[') !== false) {
                $val = $context;
                $found = true;
                $parts = $this->splitBracketElementNameParts($elementName);
                foreach ($parts as $part) {
                    if (is_array($val) && isset($val[$part])) {
                        $val = $val[$part];
                    } else {
                        $found = false;
                        break;
                    }
                }
                if ($found) {
                    $element->setValue($val, $context);
                }
            } elseif (is_array($context) && isset($context[$elementName])) {
                $element->setValue($context[$elementName], $context);
            }
        }

        return $this;
    }

    /**
     * @param string $elementName
     * @return list<string>
     */
    protected function splitBracketElementNameParts($elementName)
    {
        $parts = $this->pregSplitBracketElementName($elementName);
        if ($parts === false) {
            return array();
        }

        return $parts;
    }

    /**
     * @param string $elementName
     * @return list<string>|false
     */
    protected function pregSplitBracketElementName($elementName)
    {
        return preg_split('~[\[\]]~', $elementName, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * @return \Generator<string, mixed>
     */
    public function yieldSubValues()
    {
        foreach ($this->subEl as $element) {
            if (!$element->getIgnore()) {
                yield $element->getName() => $element->getValue();
            }
        }
    }

    /**
     * @param array<string, mixed>|null $context
     * @return bool
     */
    public function isValid($context = null)
    {
        $result = parent::isValid($context);

        foreach ($this->subEl as $element) {
            $result = $result && $element->isValid($context);
        }

        return $result;
    }
}
