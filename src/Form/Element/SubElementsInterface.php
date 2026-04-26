<?php
namespace Ufw\Form\Element;

/**
 * Composite element: nested fields sharing parent form context, sub-values, and validation.
 */
interface SubElementsInterface
{

    public function setForm(\Ufw\Form\Base $form = null);

    /**
     *
     * @param string $value            
     */
    public function setValue($value, $context = null);

    public function yieldSubValues();

    public function isValid($context);
}