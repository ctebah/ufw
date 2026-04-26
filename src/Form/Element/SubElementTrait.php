<?php
namespace Ufw\Form\Element;

/**
 * Default wiring for {@see SubElementsInterface}: propagate {@see setForm}, {@see setValue}, and {@see isValid} to child elements.
 */
trait SubElementTrait {
    
    protected $subEl = [];
    
    /**
     *
     * @param \Ufw\Form\Base $form            
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
     *
     * @param string $value            
     */
    public function setValue($value, $context = null)
    {
        parent::setValue($value, $context);
        
        foreach ($this->subEl as $element) {
            if (strpos($element->getName(), '[')) {
                $val = $context;
                $found = true;
                foreach (preg_split('~[\[\]]~', $element->getName(), -1, PREG_SPLIT_NO_EMPTY) as $part) {
                    if (isset($val[$part])) {
                        $val = $val[$part];
                    } else {
                        $found = false;
                        break;
                    }
                }
                if ($found) {
                    $element->setValue($val, $context);
                }
            } else {
                if (isset($context[$element->getName()])) {
                    $element->setValue($context[$element->getName()], $context);
                }
            }
        }
        
        return $this;
    }

    public function yieldSubValues()
    {
        foreach ($this->subEl as $element) {
            if (!$element->getIgnore()) {
                (yield $element->getName() => $element->getValue());
            }
        }
    }

    public function isValid($context = null)
    {
        $result = parent::isValid($context);
        
        foreach ($this->subEl as $element) {
            $result = $result && $element->isValid($context);
        }
        
        return $result;
    }
}
